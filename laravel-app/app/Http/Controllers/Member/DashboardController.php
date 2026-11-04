<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\LoanService;
use App\Services\SavingsService;
use App\Services\ShuService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, LoanService $loans, SavingsService $savings, ShuService $shu)
    {
        $user = $request->user();
        $account = $savings->accountFor($user);
        $activeLoan = $user->activeLoan()->first();
        $nextSchedule = $activeLoan?->schedules()->unpaid()->orderBy('installment_number')->first();

        return view('member.dashboard', [
            'user' => $user,
            'account' => $account,
            'activeLoan' => $activeLoan,
            'nextSchedule' => $nextSchedule,
            'maxLoan' => $loans->maxLoanAmount($user, (float) $account->balance),
            'eligibility' => $loans->checkEligibility($user),
            'recentTransactions' => $user->transactions()->latest()->latest('id')->limit(5)->get(),
            'notifications' => $user->appNotifications()->where('is_read', false)->latest()->limit(5)->get(),
            'pendingCount' => $user->transactions()->where('status', 'pending')->count(),
            'shu' => $shu->memberShare($user, (int) now()->year),
            'announcements' => \App\Models\Announcement::visible()->latest()->limit(3)->get(),
        ]);
    }
}
