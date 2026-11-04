<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActiveLoan;
use App\Models\LoanApplication;
use App\Models\LoanSchedule;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'pending_applications' => LoanApplication::whereIn('status', ['pending', 'reviewing'])->count(),
            'active_loans' => ActiveLoan::where('status', 'active')->count(),
            'total_outstanding' => ActiveLoan::where('status', 'active')->sum('remaining_balance'),
            'total_savings' => SavingsAccount::where('status', 'active')->sum('balance'),
            'overdue_payments' => LoanSchedule::overdue()->count(),
            'members' => User::where('role', 'nasabah')->where('status', 'active')->count(),
            'pending_verifications' => Transaction::where('status', 'pending')->count(),
        ];

        // Grafik 12 bulan terakhir, dikelompokkan di PHP agar tidak tergantung fungsi tanggal SQL.
        $start = now()->subMonths(11)->startOfMonth();
        $months = collect(range(0, 11))->map(fn ($i) => $start->copy()->addMonths($i)->format('Y-m'));
        $rows = Transaction::where('status', 'completed')->whereDate('transaction_date', '>=', $start)->get(['type', 'amount', 'transaction_date']);
        $chart = [];
        foreach (['savings_deposit', 'loan_disbursement', 'loan_payment'] as $type) {
            $byMonth = $rows->where('type', $type)->groupBy(fn ($t) => $t->transaction_date->format('Y-m'))->map->sum('amount');
            $chart[$type] = $months->map(fn ($m) => (float) ($byMonth[$m] ?? 0))->all();
        }

        return view('admin.dashboard', [
            'stats' => $stats,
            'chartLabels' => $months->map(fn ($m) => \Carbon\Carbon::createFromFormat('Y-m', $m)->locale('id')->translatedFormat('M Y'))->all(),
            'chart' => $chart,
            'pendingApplications' => LoanApplication::with('user')->whereIn('status', ['pending', 'reviewing'])->oldest()->limit(8)->get(),
            'overdue' => LoanSchedule::overdue()->with('loan.user')->orderBy('due_date')->limit(8)->get(),
            'topBorrowers' => ActiveLoan::with('user')->where('status', 'active')->orderByDesc('remaining_balance')->limit(8)->get(),
            'recentTransactions' => Transaction::with('user')->latest()->latest('id')->limit(10)->get(),
        ]);
    }
}
