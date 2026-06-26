<?php

namespace App\Http\Controllers\Api;

use App\Models\DuesPayment;
use App\Models\DuesSchedule;
use App\Models\User;
use App\Notifications\DuesPaymentNotification;
use App\Services\AutoAccountingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DuesController extends ApiController
{
    private const AMOUNT_PER_MONTH = 1000.00;

    private const DUES_START_YEAR = 2025;

    private const DUES_START_MONTH = 1;

    // ── GET /dues ─────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->generateSchedules($user);

        $now = Carbon::now();

        $unpaid = DuesSchedule::where('user_id', $user->id)
            ->where('status', 'pending')
            ->orderBy('due_year')
            ->orderBy('due_month')
            ->get(['id', 'due_year', 'due_month', 'amount', 'due_date']);

        // The last month covered by any schedule (paid or pending) in the current year
        // determines where advance months can start.
        $lastCoveredInYear = DuesSchedule::where('user_id', $user->id)
            ->where('due_year', $now->year)
            ->orderByDesc('due_month')
            ->value('due_month');

        // Advance can only start after the later of: current month, last covered month
        $floorMonth = max($lastCoveredInYear ?? 0, $now->month);
        $maxAdvance = max(0, 12 - $floorMonth);

        return $this->success([
            'unpaid' => $unpaid,
            'total_outstanding' => $unpaid->sum('amount'),
            'months_outstanding' => $unpaid->count(),
            'max_advance_months' => $maxAdvance,
            'advance_start' => $maxAdvance > 0
                ? ['year' => $now->year, 'month' => $floorMonth + 1]
                : null,
        ]);
    }

    // ── POST /dues/initialize ─────────────────────────────────────────────────

    public function initialize(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->generateSchedules($user);

        $now = Carbon::now();
        $lastCoveredInYear = DuesSchedule::where('user_id', $user->id)
            ->where('due_year', $now->year)
            ->orderByDesc('due_month')
            ->value('due_month');

        $floorMonth = max($lastCoveredInYear ?? 0, $now->month);
        $maxAdvance = max(0, 12 - $floorMonth);

        $validated = $request->validate([
            'advance_months' => ['required', 'integer', 'min:0', 'max:' . max($maxAdvance, 0)],
        ]);

        $advanceCount = $validated['advance_months'];
        $debtSchedules = $this->getDebtSchedules($user);
        $totalMonths = $debtSchedules->count() + $advanceCount;

        if ($totalMonths < 1) {
            return $this->failure('Please select at least 1 month to pay.');
        }

        $totalKobo = $totalMonths * (int) (self::AMOUNT_PER_MONTH * 100);
        $advanceSchedules = $this->generateAdvanceSchedules($user, $advanceCount, $floorMonth, $now->year);
        $allSchedules = $debtSchedules->concat($advanceSchedules);

        $reference = 'DUES-' . $user->id . '-' . time() . '-' . strtoupper(Str::random(6));

        $payment = DuesPayment::create([
            'user_id' => $user->id,
            'paystack_reference' => $reference,
            'amount_kobo' => $totalKobo,
            'months_count' => $totalMonths,
            'schedule_ids' => $allSchedules->pluck('id')->toArray(),
            'status' => 'pending',
        ]);

        $psResponse = Http::withToken(config('services.paystack.secret_key'))
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $user->email,
                'amount' => $totalKobo,
                'reference' => $reference,
                'currency' => 'NGN',
                'metadata' => [
                    'user_id' => $user->id,
                    'months_count' => $totalMonths,
                    'payment_id' => $payment->id,
                    'custom_fields' => [[
                        'display_name' => 'Member',
                        'variable_name' => 'member',
                        'value' => $user->name,
                    ]],
                ],
            ]);

        if (! $psResponse->successful() || ! $psResponse->json('status')) {
            $payment->delete();

            return $this->failure($psResponse->json('message') ?? 'Could not initialize payment. Please try again.');
        }

        $data = $psResponse->json('data');

        return $this->success([
            'access_code' => $data['access_code'],
            'reference' => $reference,
            'amount_naira' => $totalMonths * self::AMOUNT_PER_MONTH,
            'months_count' => $totalMonths,
        ]);
    }

    // ── POST /dues/verify ─────────────────────────────────────────────────────

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => ['required', 'string'],
        ]);

        $user = $request->user();

        $payment = DuesPayment::where('user_id', $user->id)
            ->where('paystack_reference', $validated['reference'])
            ->where('status', 'pending')
            ->firstOrFail();

        $psResponse = Http::withToken(config('services.paystack.secret_key'))
            ->get("https://api.paystack.co/transaction/verify/{$validated['reference']}");

        if (! $psResponse->successful()) {
            return $this->failure('Could not verify payment with Paystack.');
        }

        $data = $psResponse->json('data');

        if ($data['status'] !== 'success') {
            $payment->update(['status' => 'failed']);

            return $this->failure('Payment was not successful. Please try again.');
        }

        if ((int) $data['amount'] !== $payment->amount_kobo) {
            $payment->update(['status' => 'failed']);

            return $this->failure('Payment amount mismatch. Please contact support.');
        }

        $payment->update([
            'status' => 'success',
            'paid_at' => now(),
            'paystack_data' => $data,
        ]);

        // Settle oldest schedules first (they are already ordered oldest-first in schedule_ids)
        DuesSchedule::whereIn('id', $payment->schedule_ids)
            ->update([
                'status' => 'paid',
                'paid_at' => now(),
                'dues_payment_id' => $payment->id,
            ]);

        // Auto-record accounting income entry
        try {
            AutoAccountingService::recordIncome(
                categoryName: 'Dues & Levies',
                amount: $payment->amount_kobo / 100,
                recordedBy: $payment->user_id,
                description: "Monthly dues payment — {$payment->months_count} month(s)",
                reference: $payment->paystack_reference,
            );
        } catch (\Throwable) {
            // Non-critical: accounting entry failure should not block the payment
        }

        // Notify the payer
        try {
            $payment->user->notify(new DuesPaymentNotification($payment));
        } catch (\Throwable) {}

        return $this->success([
            'months_paid' => $payment->months_count,
            'amount_naira' => $payment->amount_kobo / 100,
            'reference' => $payment->paystack_reference,
            'paid_at' => $payment->paid_at,
        ], "Successfully paid {$payment->months_count} month(s) dues.");
    }

    // ── GET /dues/transactions ────────────────────────────────────────────────

    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();

        $payments = DuesPayment::where('user_id', $user->id)
            ->where('status', 'success')
            ->orderByDesc('paid_at')
            ->limit(30)
            ->get()
            ->map(fn (DuesPayment $p) => [
                'id' => $p->id,
                'reference' => $p->paystack_reference,
                'amount_naira' => $p->amount_kobo / 100,
                'months_count' => $p->months_count,
                'paid_at' => $p->paid_at,
            ]);

        return $this->success($payments->toArray());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Lazily generate monthly due records from start date up to current month.
     */
    private function generateSchedules(User $user): void
    {
        $globalStart = Carbon::create(self::DUES_START_YEAR, self::DUES_START_MONTH, 1)->startOfMonth();
        $userStart = Carbon::parse($user->created_at)->startOfMonth();
        $from = $globalStart->gt($userStart) ? $globalStart->copy() : $userStart->copy();
        $now = Carbon::now()->startOfMonth();

        if ($from->gt($now)) {
            return;
        }

        $existing = DuesSchedule::where('user_id', $user->id)
            ->get(['due_year', 'due_month'])
            ->map(fn ($s) => "{$s->due_year}-{$s->due_month}")
            ->flip()
            ->toArray();

        $toInsert = [];
        $current = $from->copy();

        while ($current->lte($now)) {
            $key = "{$current->year}-{$current->month}";

            if (! isset($existing[$key])) {
                $toInsert[] = [
                    'user_id' => $user->id,
                    'due_year' => $current->year,
                    'due_month' => $current->month,
                    'amount' => self::AMOUNT_PER_MONTH,
                    'due_date' => $current->toDateString(),
                    'status' => 'pending',
                    'dues_payment_id' => null,
                    'paid_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $current->addMonth();
        }

        if (! empty($toInsert)) {
            DuesSchedule::insert($toInsert);
        }
    }

    /**
     * Returns all pending debt schedules: unpaid months up to and including the current month.
     * These are always settled first in any payment.
     */
    private function getDebtSchedules(User $user): \Illuminate\Support\Collection
    {
        $now = Carbon::now();

        return DuesSchedule::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where(function ($q) use ($now) {
                $q->where('due_year', '<', $now->year)
                    ->orWhere(function ($inner) use ($now) {
                        $inner->where('due_year', $now->year)
                            ->where('due_month', '<=', $now->month);
                    });
            })
            ->orderBy('due_year')
            ->orderBy('due_month')
            ->get();
    }

    /**
     * Generates and returns pending schedule records for advance months within the current year.
     * Advance months start from ($floorMonth + 1) and are capped at December.
     */
    private function generateAdvanceSchedules(User $user, int $count, int $floorMonth, int $year): \Illuminate\Support\Collection
    {
        if ($count <= 0) {
            return collect();
        }

        $toInsert = [];
        $targetMonths = [];

        for ($i = 1; $i <= $count; $i++) {
            $month = $floorMonth + $i;

            if ($month > 12) {
                break;
            }

            $targetMonths[] = $month;

            $exists = DuesSchedule::where('user_id', $user->id)
                ->where('due_year', $year)
                ->where('due_month', $month)
                ->exists();

            if (! $exists) {
                $toInsert[] = [
                    'user_id' => $user->id,
                    'due_year' => $year,
                    'due_month' => $month,
                    'amount' => self::AMOUNT_PER_MONTH,
                    'due_date' => Carbon::create($year, $month, 1)->toDateString(),
                    'status' => 'pending',
                    'dues_payment_id' => null,
                    'paid_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if (! empty($toInsert)) {
            DuesSchedule::insert($toInsert);
        }

        return DuesSchedule::where('user_id', $user->id)
            ->where('due_year', $year)
            ->whereIn('due_month', $targetMonths)
            ->where('status', 'pending')
            ->orderBy('due_month')
            ->get();
    }
}
