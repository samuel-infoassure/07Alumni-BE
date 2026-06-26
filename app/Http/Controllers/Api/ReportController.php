<?php

namespace App\Http\Controllers\Api;

use App\Models\AccountTransaction;
use App\Models\Donation;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends ApiController
{
    public function index(Request $request)
    {
        $year = $request->integer('year', now()->year);

        $totalMembers = User::count();

        $meetingStats = [
            'total' => Meeting::count(),
            'this_year' => Meeting::whereYear('meeting_date', $year)->count(),
            'completed' => Meeting::where('status', 'completed')->count(),
        ];

        $eventStats = [
            'total' => Event::count(),
            'this_year' => Event::whereYear('event_date', $year)->count(),
            'upcoming' => Event::where('status', 'upcoming')->count(),
        ];

        $donationStats = [
            'total_raised' => Donation::where('status', 'completed')->sum('amount'),
            'this_year' => Donation::where('status', 'completed')->whereYear('donated_at', $year)->sum('amount'),
            'donor_count' => Donation::where('status', 'completed')->distinct('user_id')->count('user_id'),
        ];

        $financialStats = [
            'total_income' => AccountTransaction::where('type', 'income')->whereYear('transaction_date', $year)->sum('amount'),
            'total_expense' => AccountTransaction::where('type', 'expense')->whereYear('transaction_date', $year)->sum('amount'),
        ];

        $financialStats['balance'] = $financialStats['total_income'] - $financialStats['total_expense'];

        $monthlyDonations = Donation::select(
            DB::raw('MONTH(donated_at) as month'),
            DB::raw('SUM(amount) as total')
        )
            ->where('status', 'completed')
            ->whereYear('donated_at', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $recentActivity = collect([
            ...Meeting::latest()->limit(3)->get()->map(fn ($m) => [
                'type' => 'meeting',
                'title' => $m->title,
                'date' => $m->meeting_date,
                'status' => $m->status,
            ])->toArray(),
            ...Event::latest()->limit(3)->get()->map(fn ($e) => [
                'type' => 'event',
                'title' => $e->title,
                'date' => $e->event_date,
                'status' => $e->status,
            ])->toArray(),
            ...Donation::with('user:id,first_name,last_name')->where('status', 'completed')
                ->latest()->limit(3)->get()->map(fn ($d) => [
                    'type' => 'donation',
                    'title' => 'Donation by '.$d->user->name,
                    'date' => $d->donated_at,
                    'amount' => $d->amount,
                ])->toArray(),
        ])->sortByDesc('date')->values()->take(8);

        return $this->success([
            'year' => $year,
            'total_members' => $totalMembers,
            'meetings' => $meetingStats,
            'events' => $eventStats,
            'donations' => $donationStats,
            'financials' => $financialStats,
            'monthly_donations' => $monthlyDonations->toArray(),
            'recent_activity' => $recentActivity->toArray(),
        ], 'Reports loaded.');
    }
}
