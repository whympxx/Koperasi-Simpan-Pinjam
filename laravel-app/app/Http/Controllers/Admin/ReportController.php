<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActiveLoan;
use App\Models\LoanApplication;
use App\Models\LoanSchedule;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
        $start = $data['start_date'] ?? now()->startOfMonth()->toDateString();
        $end = $data['end_date'] ?? today()->toDateString();

        $summary = Transaction::where('status', 'completed')
            ->whereDate('transaction_date', '>=', $start)->whereDate('transaction_date', '<=', $end)
            ->selectRaw('type, COUNT(*) as count, SUM(amount) as total')
            ->groupBy('type')->get()->keyBy('type');

        $interest = LoanSchedule::where('status', 'paid')->whereDate('paid_date', '>=', $start)->whereDate('paid_date', '<=', $end)
            ->selectRaw('SUM(interest_amount) as interest, SUM(late_fee) as late_fee, SUM(principal_amount) as principal')
            ->first();

        return view('admin.reports', [
            'start' => $start,
            'end' => $end,
            'summary' => $summary,
            'interest' => $interest,
            'portfolio' => [
                'total_savings' => SavingsAccount::where('status', 'active')->sum('balance'),
                'total_outstanding' => ActiveLoan::where('status', 'active')->sum('remaining_balance'),
                'active_loans' => ActiveLoan::where('status', 'active')->count(),
                'pending_apps' => LoanApplication::whereIn('status', ['pending', 'reviewing'])->count(),
                'overdue_amount' => LoanSchedule::overdue()->sum('total_amount'),
            ],
            'applications' => LoanApplication::whereBetween('created_at', [$start.' 00:00:00', $end.' 23:59:59'])
                ->selectRaw('status, COUNT(*) as count, SUM(loan_amount) as total')->groupBy('status')->get()->keyBy('status'),
        ]);
    }
}
