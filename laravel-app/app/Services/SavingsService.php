<?php

namespace App\Services;

use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Simpanan wajib anggota. Simpanan bersifat beku selama masih anggota
 * (RAT 2025); penarikan hanya lewat proses keluar keanggotaan oleh pengurus.
 */
class SavingsService
{
    public function accountFor(User $user): SavingsAccount
    {
        return $user->savingsAccount()->first() ?? $this->createAccount($user);
    }

    public function createAccount(User $user): SavingsAccount
    {
        do {
            $number = '7'.now()->format('y').str_pad((string) random_int(1, 9999999), 7, '0', STR_PAD_LEFT);
        } while (SavingsAccount::where('account_number', $number)->exists());

        $account = SavingsAccount::create([
            'user_id' => $user->id,
            'account_number' => $number,
            'balance' => 0,
            'status' => 'active',
            'opened_date' => today(),
        ]);

        Audit::log($user->id, 'savings_account_created', 'savings_account', $account->id, null, ['account_number' => $number]);

        return $account;
    }

    /**
     * Setoran simpanan wajib. Setoran dari nasabah menunggu verifikasi pengurus;
     * setoran yang dicatat pengurus ($admin) langsung menambah saldo.
     */
    public function deposit(User $member, float $amount, string $method, ?string $description = null, ?User $admin = null): Transaction
    {
        $min = (float) config('koperasi.min_deposit_amount');
        $max = (float) config('koperasi.max_deposit_amount');
        if ($amount < $min || $amount > $max) {
            throw new RuntimeException('Jumlah setoran harus antara '.rupiah($min).' dan '.rupiah($max).'.');
        }

        return DB::transaction(function () use ($member, $amount, $method, $description, $admin) {
            $account = $this->accountFor($member);

            $transaction = Transaction::create([
                'user_id' => $member->id,
                'reference_number' => Audit::reference('DEP', Transaction::class, 'reference_number'),
                'type' => 'savings_deposit',
                'amount' => $amount,
                'description' => $description ?: 'Setoran simpanan wajib',
                'related_account_id' => $account->id,
                'payment_method' => $method,
                'status' => 'pending',
                'transaction_date' => today()->toDateString(),
            ]);

            if ($admin) {
                $this->confirmDeposit($transaction, $admin);
            } else {
                Audit::notifyAdmins('Setoran Simpanan Perlu Verifikasi',
                    $member->full_name.' mengirim setoran '.rupiah($amount).'.',
                    'warning', 'account_activity', route('admin.verifications.index', absolute: false));
                Audit::log($member->id, 'savings_deposit_submitted', 'transaction', $transaction->id, null, ['amount' => $amount]);
            }

            return $transaction->fresh();
        });
    }

    public function confirmDeposit(Transaction $transaction, User $admin): void
    {
        DB::transaction(function () use ($transaction, $admin) {
            $transaction = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($transaction->type !== 'savings_deposit' || $transaction->status !== 'pending') {
                throw new RuntimeException('Transaksi tidak ditemukan atau sudah diproses.');
            }

            $account = SavingsAccount::whereKey($transaction->related_account_id)->lockForUpdate()->firstOrFail();
            if ($account->status !== 'active') {
                throw new RuntimeException('Rekening simpanan sudah tidak aktif.');
            }
            $before = (float) $account->balance;
            $after = round($before + (float) $transaction->amount, 2);

            $account->update(['balance' => $after, 'last_transaction_at' => now()]);
            $transaction->update([
                'status' => 'completed',
                'balance_before' => $before,
                'balance_after' => $after,
                'processed_by' => $admin->id,
                'verified_at' => now(),
            ]);

            Audit::notify($transaction->user_id, 'Setoran Diterima',
                'Setoran '.rupiah($transaction->amount).' telah dibukukan. Saldo simpanan wajib: '.rupiah($after).'.',
                'success', 'account_activity', route('savings.index', absolute: false));
            Audit::log($admin->id, 'savings_deposit_confirmed', 'transaction', $transaction->id, ['balance' => $before], ['balance' => $after]);
        });
    }

    /** Tolak setoran / pembayaran angsuran yang menunggu verifikasi. */
    public function rejectPending(Transaction $transaction, User $admin, string $reason): void
    {
        DB::transaction(function () use ($transaction, $admin, $reason) {
            $transaction = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($transaction->status !== 'pending') {
                throw new RuntimeException('Transaksi sudah diproses.');
            }

            $transaction->update(['status' => 'failed', 'processed_by' => $admin->id, 'verified_at' => now(), 'notes' => $reason]);

            Audit::notify($transaction->user_id, $transaction->typeLabel().' Ditolak',
                $transaction->typeLabel().' '.rupiah($transaction->amount).' ('.$transaction->reference_number.') ditolak. Alasan: '.$reason,
                'error', 'account_activity');
            Audit::log($admin->id, 'transaction_rejected', 'transaction', $transaction->id, ['status' => 'pending'], ['status' => 'failed', 'reason' => $reason]);
        });
    }

    /**
     * Proses keluar keanggotaan: seluruh simpanan wajib dikembalikan,
     * rekening ditutup, dan status anggota menjadi "resigned".
     */
    public function closeMembership(User $member, User $admin, string $method, ?string $notes = null): ?Transaction
    {
        return DB::transaction(function () use ($member, $admin, $method, $notes) {
            $member = User::whereKey($member->id)->lockForUpdate()->firstOrFail();

            if (! $member->isNasabah()) {
                throw new RuntimeException('Hanya akun nasabah yang dapat diproses keluar keanggotaan.');
            }
            if ($member->activeLoan()->exists()) {
                throw new RuntimeException('Anggota masih memiliki pinjaman aktif. Lunasi pinjaman terlebih dahulu.');
            }
            if ($member->loanApplications()->whereIn('status', ['pending', 'reviewing'])->exists()) {
                throw new RuntimeException('Anggota masih memiliki pengajuan pinjaman yang diproses.');
            }
            if (Transaction::where('user_id', $member->id)->where('status', 'pending')->exists()) {
                throw new RuntimeException('Masih ada transaksi anggota yang menunggu verifikasi.');
            }

            $transaction = null;
            $account = SavingsAccount::where('user_id', $member->id)->where('status', 'active')->lockForUpdate()->first();
            if ($account) {
                $balance = (float) $account->balance;
                if ($balance > 0) {
                    $transaction = Transaction::create([
                        'user_id' => $member->id,
                        'reference_number' => Audit::reference('WDR', Transaction::class, 'reference_number'),
                        'type' => 'savings_withdrawal',
                        'amount' => $balance,
                        'description' => 'Pengembalian simpanan wajib (keluar keanggotaan)',
                        'related_account_id' => $account->id,
                        'balance_before' => $balance,
                        'balance_after' => 0,
                        'payment_method' => $method,
                        'processed_by' => $admin->id,
                        'status' => 'completed',
                        'notes' => $notes,
                        'verified_at' => now(),
                        'transaction_date' => today()->toDateString(),
                    ]);
                }
                $account->update(['balance' => 0, 'status' => 'closed', 'closed_date' => today(), 'last_transaction_at' => now()]);
            }

            $member->update(['status' => 'resigned']);

            Audit::notify($member->id, 'Keanggotaan Berakhir',
                'Keanggotaan Anda telah diakhiri. Simpanan wajib '.rupiah($transaction?->amount ?? 0).' dikembalikan.',
                'info', 'account_activity');
            Audit::log($admin->id, 'membership_closed', 'user', $member->id, ['status' => 'active'], [
                'status' => 'resigned', 'refund' => $transaction?->amount ?? 0, 'notes' => $notes,
            ]);

            return $transaction;
        });
    }
}
