<?php

namespace App\Http\Controllers\Api;

use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\User;
use App\Notifications\DonationNotification;
use App\Services\AutoAccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class DonationController extends ApiController
{
    public function campaigns()
    {
        $campaigns = DonationCampaign::with('creator:id,first_name,last_name')
            ->withCount('donations')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DonationCampaign $c) => [
                ...$c->toArray(),
                'progress_percent' => $c->getProgressPercentAttribute(),
            ]);

        return $this->success($campaigns->toArray(), 'Campaigns loaded.');
    }

    public function index()
    {
        $donations = Donation::with(['user:id,first_name,last_name', 'campaign:id,title'])
            ->orderByDesc('donated_at')
            ->get();

        $totalRaised = $donations->where('status', 'completed')->sum('amount');

        return $this->success([
            'donations' => $donations->toArray(),
            'total_raised' => $totalRaised,
        ], 'Donations loaded.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'campaign_id' => ['nullable', 'integer', 'exists:donation_campaigns,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_method' => ['required', 'in:bank_transfer,cash,online,other'],
            'note' => ['nullable', 'string'],
            'status' => ['nullable', 'in:pending,completed,failed'],
        ]);

        $donation = Donation::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'reference' => strtoupper(Str::random(12)),
            'status' => $validated['status'] ?? 'pending',
        ]);

        if ($donation->campaign_id && $donation->status === 'completed') {
            DonationCampaign::where('id', $donation->campaign_id)
                ->increment('current_amount', $donation->amount);
        }

        // Auto-record accounting income entry
        if ($donation->status === 'completed') {
            try {
                AutoAccountingService::recordIncome(
                    categoryName: 'Donations Received',
                    amount: $donation->amount,
                    recordedBy: $donation->user_id,
                    description: 'Donation' . ($donation->campaign_id ? " (Campaign #{$donation->campaign_id})" : ''),
                    reference: $donation->reference,
                );
            } catch (\Throwable) {}

            // Notify the donor
            try {
                $donation->load('user');
                $donation->user->notify(new DonationNotification($donation, 'donor'));
            } catch (\Throwable) {}

            // Notify treasurers (users with accounting.view permission)
            try {
                $treasurers = User::whereHas('roles.permissions', fn ($q) => $q->where('name', 'accounting.view'))
                    ->where('id', '!=', $donation->user_id)
                    ->get();
                Notification::send($treasurers, new DonationNotification($donation, 'treasurer'));
            } catch (\Throwable) {}
        }

        return $this->success($donation->toArray(), 'Donation recorded.', 201);
    }

    public function show(int $id)
    {
        $campaign = DonationCampaign::with([
            'creator:id,first_name,last_name',
            'donations.user:id,first_name,last_name',
        ])->withCount('donations')->findOrFail($id);

        return $this->success([
            ...$campaign->toArray(),
            'progress_percent' => $campaign->getProgressPercentAttribute(),
        ], 'Campaign details loaded.');
    }

    public function update(Request $request, int $id)
    {
        $campaign = DonationCampaign::findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_amount' => ['sometimes', 'numeric', 'min:1'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:active,completed,cancelled'],
        ]);

        $campaign->update($validated);

        return $this->success($campaign->toArray(), 'Campaign updated.');
    }

    public function destroy(int $id)
    {
        DonationCampaign::findOrFail($id)->delete();

        return $this->success([], 'Campaign deleted.');
    }

    public function createCampaign(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'target_amount' => ['required', 'numeric', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'cover_image' => ['nullable', 'string', 'url'],
        ]);

        $campaign = DonationCampaign::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return $this->success($campaign->toArray(), 'Campaign created.', 201);
    }
}
