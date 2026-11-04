<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActiveLoan;
use App\Models\LoanSchedule;
use App\Models\Transaction;
use App\Services\LoanService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class ActiveLoanController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'active');
        $search = trim((string) $request->query('search', ''));

        $loans = ActiveLoan::with('user')
            ->withCount(['schedules as overdue_count' => fn ($q) => $q->overdue()])
            ->when(in_array($status, ['active', 'completed'], true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('loan_number', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('full_name', 'like', "%{$search}%")->orWhere('nik', 'like', "%{$search}%"))))
            ->latest()->paginate(15)->withQueryString();

        return view('admin.active-loans.index', [
            'loans' => $loans,
            'status' => $status,
            'search' => $search,
            'outstanding' => ActiveLoan::where('status', 'active')->sum('remaining_balance'),
        ]);
    }

    public function show(ActiveLoan $loan)
    {
        $loan->load(['user', 'application', 'schedules']);
        $pending = Transaction::where('related_loan_id', $loan->id)->where('status', 'pending')->pluck('related_schedule_id')->all();

        return view('admin.active-loans.show', [
            'loan' => $loan,
            'pendingScheduleIds' => $pending,
            'payoff' => $loan->status === 'active' ? app(LoanService::class)->payoffQuote($loan) : null,
        ]);
    }

    public function payoff(Request $request, ActiveLoan $loan, LoanService $loans)
    {
        $data = $request->validate(['payment_method' => ['required', Rule::in(array_keys(Transaction::METHODS))]]);

        try {
            $tx = $loans->payoff($loan, $request->user(), $data['payment_method']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pinjaman '.$loan->loan_number.' lunas dengan pelunasan dipercepat sebesar '.rupiah($tx->amount).'.');
    }

    /** Pengurus mencatat pembayaran (mis. potong gaji / tunai di kantor) — langsung dibukukan. */
    public function recordPayment(Request $request, LoanSchedule $schedule, LoanService $loans)
    {
        $data = $request->validate(['payment_method' => ['required', Rule::in(array_keys(Transaction::METHODS))]]);

        try {
            $loans->pay($schedule->loan->user, $schedule, $data['payment_method'], $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pembayaran angsuran ke-'.$schedule->installment_number.' telah dibukukan.');
    }
}
