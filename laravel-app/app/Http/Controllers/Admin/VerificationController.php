<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\LoanService;
use App\Services\SavingsService;
use Illuminate\Http\Request;
use RuntimeException;

/** Verifikasi setoran simpanan & pembayaran angsuran yang dikirim nasabah. */
class VerificationController extends Controller
{
    public function index()
    {
        return view('admin.verifications', [
            'transactions' => Transaction::with(['user', 'schedule'])->where('status', 'pending')->oldest()->paginate(20),
        ]);
    }

    public function approve(Request $request, Transaction $transaction, SavingsService $savings, LoanService $loans)
    {
        try {
            match ($transaction->type) {
                'savings_deposit' => $savings->confirmDeposit($transaction, $request->user()),
                'loan_payment' => $loans->confirmPayment($transaction, $request->user()),
                default => throw new RuntimeException('Jenis transaksi ini tidak memerlukan verifikasi.'),
            };
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $transaction->typeLabel().' '.$transaction->reference_number.' telah diverifikasi.');
    }

    public function reject(Request $request, Transaction $transaction, SavingsService $savings)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => 'alasan penolakan']);

        try {
            $savings->rejectPending($transaction, $request->user(), $data['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transaksi '.$transaction->reference_number.' ditolak.');
    }
}
