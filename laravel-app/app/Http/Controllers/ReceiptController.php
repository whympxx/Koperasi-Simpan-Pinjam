<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

/** Kwitansi / bukti transaksi yang dapat dicetak. */
class ReceiptController extends Controller
{
    public function show(Request $request, Transaction $transaction)
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $transaction->user_id === $user->id, 404);
        abort_unless($transaction->status === 'completed', 404, 'Kwitansi hanya tersedia untuk transaksi yang sudah selesai.');

        $transaction->load(['user.division', 'processor', 'loan', 'schedule']);

        return view('receipt', ['t' => $transaction]);
    }
}
