<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\SavingsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class SavingsController extends Controller
{
    private const SAVINGS_TYPES = ['savings_deposit', 'savings_withdrawal', 'interest_credit'];

    public function index(Request $request, SavingsService $savings)
    {
        $user = $request->user();
        $account = $savings->accountFor($user);

        $base = $user->transactions()->whereIn('type', self::SAVINGS_TYPES);

        return view('member.savings.index', [
            'account' => $account,
            'totalDeposits' => (clone $base)->where('type', 'savings_deposit')->where('status', 'completed')->sum('amount'),
            'pendingDeposits' => (clone $base)->where('type', 'savings_deposit')->where('status', 'pending')->sum('amount'),
            'transactions' => (clone $base)->latest()->latest('id')->paginate(15),
        ]);
    }

    public function create(Request $request, SavingsService $savings)
    {
        return view('member.savings.create', [
            'account' => $savings->accountFor($request->user()),
            'methods' => array_diff_key(Transaction::METHODS, ['payroll_deduction' => true]),
        ]);
    }

    public function store(Request $request, SavingsService $savings)
    {
        $request->merge(['amount' => parse_money($request->input('amount'))]);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:'.config('koperasi.min_deposit_amount'), 'max:'.config('koperasi.max_deposit_amount')],
            'payment_method' => ['required', Rule::in(['transfer', 'cash', 'online'])],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], ['amount' => 'jumlah setoran', 'payment_method' => 'metode pembayaran']);

        try {
            $tx = $savings->deposit($request->user(), (float) $data['amount'], $data['payment_method'], $data['description'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route('savings.index')
            ->with('success', 'Setoran '.rupiah($tx->amount).' dikirim (No. '.$tx->reference_number.'). Saldo akan bertambah setelah diverifikasi pengurus.');
    }

    public function statement(Request $request, SavingsService $savings)
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $from = $data['from'] ?? now()->startOfYear()->toDateString();
        $to = $data['to'] ?? today()->toDateString();

        $user = $request->user();
        $transactions = $user->transactions()
            ->whereIn('type', self::SAVINGS_TYPES)
            ->where('status', 'completed')
            ->whereDate('transaction_date', '>=', $from)->whereDate('transaction_date', '<=', $to)
            ->orderBy('transaction_date')->orderBy('id')
            ->get();

        return view('member.savings.statement', [
            'user' => $user,
            'account' => $savings->accountFor($user),
            'transactions' => $transactions,
            'from' => $from,
            'to' => $to,
            'totalIn' => $transactions->whereIn('type', ['savings_deposit', 'interest_credit'])->sum('amount'),
            'totalOut' => $transactions->where('type', 'savings_withdrawal')->sum('amount'),
        ]);
    }
}
