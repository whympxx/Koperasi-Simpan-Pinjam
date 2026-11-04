<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\LoanSchedule;
use App\Models\Transaction;
use App\Services\LoanService;
use App\Services\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class LoanController extends Controller
{
    public function index(Request $request, LoanService $loans)
    {
        $user = $request->user();
        $activeLoan = $user->activeLoan()->with('schedules')->first();

        return view('member.loans.index', [
            'applications' => $user->loanApplications()->latest()->latest('id')->get(),
            'activeLoan' => $activeLoan,
            'pendingScheduleIds' => $this->pendingScheduleIds($user->id),
            'eligibility' => $loans->checkEligibility($user),
            'payoff' => $activeLoan ? $loans->payoffQuote($activeLoan) : null,
        ]);
    }

    public function create(Request $request, LoanService $loans)
    {
        $user = $request->user();
        $eligibility = $loans->checkEligibility($user);

        if (! $eligibility['eligible']) {
            return redirect()->route('loans.index')->with('error', 'Belum dapat mengajukan pinjaman: '.$eligibility['reason']);
        }

        $savings = $loans->savingsBalance($user);

        return view('member.loans.create', [
            'user' => $user,
            'savings' => $savings,
            'multiplier' => $loans->multiplier($user),
            'maxLoan' => $loans->maxLoanAmount($user, $savings),
            'minLoan' => Settings::float('min_loan_amount'),
            'maxTerm' => Settings::int('max_loan_term'),
            'standardTerm' => Settings::int('max_loan_term_standard'),
            'interestRate' => Settings::float('loan_interest_rate'),
        ]);
    }

    public function store(Request $request, LoanService $loans)
    {
        $request->merge(['loan_amount' => parse_money($request->input('loan_amount'))]);
        $data = $request->validate([
            'loan_amount' => ['required', 'numeric', 'min:1'],
            'term_months' => ['required', 'integer', 'min:1', 'max:'.Settings::int('max_loan_term')],
            'purpose' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'agreement' => ['accepted'],
        ], [
            'agreement.accepted' => 'Anda harus menyetujui syarat dan ketentuan pinjaman.',
        ], ['loan_amount' => 'jumlah pinjaman', 'term_months' => 'jangka waktu', 'purpose' => 'tujuan pinjaman']);

        try {
            $application = $loans->submit($request->user(), (float) $data['loan_amount'], (int) $data['term_months'], $data['purpose'], $data['description'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $message = $application->status === 'disbursed'
            ? 'Selamat! Pinjaman '.rupiah($application->loan_amount).' disetujui otomatis dan telah dicairkan.'
            : 'Pengajuan '.$application->application_number.' berhasil dikirim dan menunggu review pengurus. Catatan: '.$application->review_notes;

        return redirect()->route('loans.index')->with('success', $message);
    }

    public function payment(Request $request)
    {
        $loan = $request->user()->activeLoan()->with('schedules')->first();
        if (! $loan) {
            return redirect()->route('loans.index')->with('error', 'Anda tidak memiliki pinjaman aktif.');
        }

        $next = $loan->schedules->firstWhere('status', '!=', 'paid');

        return view('member.loans.payment', [
            'loan' => $loan,
            'next' => $next,
            'lateFee' => $next ? LoanService::lateFee($next) : 0,
            'pendingScheduleIds' => $this->pendingScheduleIds($request->user()->id),
            'methods' => array_diff_key(Transaction::METHODS, ['payroll_deduction' => true]),
        ]);
    }

    public function pay(Request $request, LoanService $loans)
    {
        $data = $request->validate([
            'schedule_id' => ['required', 'integer'],
            'payment_method' => ['required', Rule::in(['transfer', 'cash', 'online'])],
        ]);

        $schedule = LoanSchedule::find($data['schedule_id']);
        if (! $schedule) {
            return back()->with('error', 'Jadwal angsuran tidak ditemukan.');
        }

        try {
            $tx = $loans->pay($request->user(), $schedule, $data['payment_method']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('loans.index')
            ->with('success', 'Pembayaran '.rupiah($tx->amount).' dikirim (No. '.$tx->reference_number.') dan menunggu verifikasi pengurus.');
    }

    private function pendingScheduleIds(int $userId): array
    {
        return Transaction::where('user_id', $userId)->where('type', 'loan_payment')->where('status', 'pending')
            ->pluck('related_schedule_id')->filter()->all();
    }
}
