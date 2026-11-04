<?php

namespace App\Services;

use App\Models\ActiveLoan;
use App\Models\LoanApplication;
use App\Models\LoanSchedule;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LoanService
{
    public function __construct(private RuleEngine $engine)
    {
    }

    /** Angsuran bunga flat: (pokok / tenor) + (pokok x bunga per bulan). */
    public static function installment(float $amount, float $ratePercent, int $term): float
    {
        if ($term < 1) {
            return 0;
        }

        return round($amount / $term + $amount * $ratePercent / 100, 2);
    }

    public function multiplier(User $user): float
    {
        return $user->hasPayrollDeduction()
            ? Settings::float('max_loan_multiplier')
            : Settings::float('max_loan_multiplier_limited');
    }

    public function savingsBalance(User $user): float
    {
        return (float) ($user->savingsAccount()->value('balance') ?? 0);
    }

    public function maxLoanAmount(User $user, ?float $savings = null): float
    {
        $savings ??= $this->savingsBalance($user);

        return max(0, floor($savings * $this->multiplier($user)));
    }

    /** Fakta nasabah (dan pengajuan, bila nominal & tenor diketahui) untuk rule engine. */
    public function facts(User $user, ?float $amount = null, ?int $term = null): array
    {
        $salary = (float) $user->monthly_salary;
        $savings = $this->savingsBalance($user);

        $facts = [
            'masa_kerja_bulan' => $user->employmentMonths(),
            'status_karyawan' => $user->status,
            'kategori_anggota' => $user->member_category,
            'potongan_hrd' => $user->hasPayrollDeduction() ? 1 : 0,
            'gaji_bulanan' => $salary,
            'simpanan_wajib' => $savings,
            'batas_kelipatan' => $this->multiplier($user),
            'plafon_maksimal' => $this->maxLoanAmount($user, $savings),
            'punya_pinjaman_aktif' => $user->activeLoan()->exists() ? 1 : 0,
            'punya_pengajuan_pending' => $user->loanApplications()->whereIn('status', ['pending', 'reviewing'])->exists() ? 1 : 0,
            'riwayat_tunggakan' => $this->latePaymentCount($user),
            'pinjaman_lunas' => ActiveLoan::where('user_id', $user->id)->where('status', 'completed')->count(),
            'auto_approve_aktif' => Settings::bool('loan_auto_approve_enabled') ? 1 : 0,
        ];

        if ($amount !== null && $term !== null && $amount > 0 && $term > 0) {
            $installment = self::installment($amount, Settings::float('loan_interest_rate'), $term);
            $facts += [
                'jumlah_pinjaman' => $amount,
                'tenor_bulan' => $term,
                'angsuran_bulanan' => $installment,
                'kelipatan_simpanan' => $savings > 0 ? round($amount / $savings, 2) : 999,
                'kelipatan_gaji' => $salary > 0 ? round($amount / $salary, 2) : 0,
                'rasio_angsuran_gaji' => $salary > 0 ? round($installment / $salary * 100, 2) : 0,
            ];
        }

        return $facts;
    }

    public function latePaymentCount(User $user): int
    {
        return LoanSchedule::whereHas('loan', fn ($q) => $q->where('user_id', $user->id))
            ->where(function ($q) {
                $q->whereColumn('paid_date', '>', 'due_date')
                    ->orWhere(fn ($q) => $q->where('status', '!=', 'paid')->whereDate('due_date', '<', today()));
            })
            ->count();
    }

    /** Validasi batas formulir (BVA): nominal minimum, plafon, dan rentang tenor. */
    public function validateForm(float $amount, int $term, float $maxLoan): ?string
    {
        $min = Settings::float('min_loan_amount');
        $maxTerm = Settings::int('max_loan_term');

        if ($amount < $min) {
            return 'Jumlah pinjaman minimal '.rupiah($min).'.';
        }
        if ($maxLoan <= 0) {
            return 'Plafon pinjaman belum tersedia. Pastikan simpanan wajib minimal '.rupiah(Settings::float('min_mandatory_savings')).'.';
        }
        if ($amount > $maxLoan) {
            return 'Jumlah pinjaman melebihi batas maksimal '.rupiah($maxLoan).'.';
        }
        if ($term < 1 || $term > $maxTerm) {
            return 'Jangka waktu harus antara 1-'.$maxTerm.' bulan.';
        }

        return null;
    }

    /**
     * @return array{eligible: bool, reason: string, rule_code: ?string, evaluation: ?array}
     */
    public function checkEligibility(User $user, ?float $amount = null, ?int $term = null): array
    {
        $facts = $this->facts($user, $amount, $term);

        if (! $this->engine->hasActiveRules(RuleEngine::GROUP_ELIGIBILITY)) {
            return $this->legacyEligibility($facts);
        }

        $evaluation = $this->engine->evaluate(RuleEngine::GROUP_ELIGIBILITY, $facts, 'eligible', 'Memenuhi seluruh syarat pengajuan.');

        return [
            'eligible' => $evaluation['decision'] !== 'reject',
            'reason' => $evaluation['decision'] === 'reject' ? $evaluation['message'] : '',
            'rule_code' => $evaluation['rule_code'],
            'evaluation' => $evaluation,
        ];
    }

    /** Aturan bawaan bila seluruh aturan kelayakan dinonaktifkan/dihapus. */
    private function legacyEligibility(array $facts): array
    {
        $minMonths = Settings::int('min_employment_months');
        $minSavings = Settings::float('min_mandatory_savings');

        $reason = match (true) {
            $facts['masa_kerja_bulan'] < $minMonths => "Masa kerja minimal {$minMonths} bulan. Masa kerja Anda: {$facts['masa_kerja_bulan']} bulan.",
            $facts['punya_pinjaman_aktif'] === 1 => 'Anda sudah memiliki pinjaman aktif. Selesaikan pinjaman yang ada terlebih dahulu.',
            $facts['punya_pengajuan_pending'] === 1 => 'Anda memiliki pengajuan pinjaman yang sedang diproses.',
            $facts['status_karyawan'] !== 'active' => 'Status kepegawaian Anda tidak aktif.',
            $facts['simpanan_wajib'] < $minSavings => 'Simpanan wajib minimal '.rupiah($minSavings).'.',
            isset($facts['jumlah_pinjaman']) && $facts['jumlah_pinjaman'] > $facts['plafon_maksimal'] => 'Jumlah pinjaman melebihi plafon maksimal '.rupiah($facts['plafon_maksimal']).'.',
            isset($facts['tenor_bulan']) && $facts['tenor_bulan'] > Settings::int('max_loan_term') => 'Jangka waktu pinjaman maksimal '.Settings::int('max_loan_term').' bulan.',
            default => '',
        };

        return ['eligible' => $reason === '', 'reason' => $reason, 'rule_code' => null, 'evaluation' => null];
    }

    /**
     * @return array{auto_approve: bool, rule_code: ?string, message: string, evaluation: ?array}
     */
    public function evaluateApproval(User $user, float $amount, int $term): array
    {
        if (! $this->engine->hasActiveRules(RuleEngine::GROUP_DECISION)) {
            $auto = Settings::bool('loan_auto_approve_enabled')
                && $user->hasPayrollDeduction()
                && $amount <= Settings::float('loan_auto_approve_max_amount')
                && $term <= Settings::int('loan_auto_approve_max_term_months')
                && $term <= Settings::int('max_loan_term_standard');

            return [
                'auto_approve' => $auto,
                'rule_code' => null,
                'message' => $auto ? 'Disetujui otomatis oleh sistem (memenuhi kriteria auto-approve).' : 'Pengajuan diteruskan ke review admin.',
                'evaluation' => null,
            ];
        }

        $evaluation = $this->engine->evaluate(
            RuleEngine::GROUP_DECISION,
            $this->facts($user, $amount, $term),
            'manual_review',
            'Tidak ada aturan persetujuan otomatis yang terpenuhi, pengajuan diteruskan ke review admin.'
        );

        return [
            'auto_approve' => $evaluation['decision'] === 'approve',
            'rule_code' => $evaluation['rule_code'],
            'message' => $evaluation['message'],
            'evaluation' => $evaluation,
        ];
    }

    /**
     * Ajukan pinjaman. Aplikasi yang lolos aturan keputusan langsung disetujui & dicairkan.
     *
     * @throws RuntimeException bila tidak memenuhi syarat
     */
    public function submit(User $user, float $amount, int $term, string $purpose, ?string $description = null): LoanApplication
    {
        return DB::transaction(function () use ($user, $amount, $term, $purpose, $description) {
            // Kunci baris user agar dua submit bersamaan tidak sama-sama lolos cek "pengajuan pending".
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $error = $this->validateForm($amount, $term, $this->maxLoanAmount($user));
            if ($error) {
                throw new RuntimeException($error);
            }

            $eligibility = $this->checkEligibility($user, $amount, $term);
            if (! $eligibility['eligible']) {
                if ($eligibility['evaluation']) {
                    $this->engine->log($eligibility['evaluation'], $user->id, 'loan_application');
                }
                throw new RuntimeException($eligibility['reason']);
            }

            $rate = Settings::float('loan_interest_rate');
            $installment = self::installment($amount, $rate, $term);
            $approval = $this->evaluateApproval($user, $amount, $term);

            $application = LoanApplication::create([
                'user_id' => $user->id,
                'application_number' => Audit::reference('LOAN', LoanApplication::class, 'application_number'),
                'loan_amount' => $amount,
                'interest_rate' => $rate,
                'loan_term_months' => $term,
                'monthly_installment' => $installment,
                'total_payment' => round($installment * $term, 2),
                'purpose' => $purpose,
                'description' => $description,
                'status' => 'pending',
                'decision_rule_code' => $approval['rule_code'],
                'review_notes' => ($approval['rule_code'] ? '['.$approval['rule_code'].'] ' : '').$approval['message'],
            ]);

            foreach ([$eligibility['evaluation'], $approval['evaluation']] as $evaluation) {
                if ($evaluation) {
                    $this->engine->log($evaluation, $user->id, 'loan_application', $application->id);
                }
            }

            Audit::log($user->id, 'loan_application_submitted', 'loan_application', $application->id, null, [
                'amount' => $amount, 'term' => $term, 'auto_approved' => $approval['auto_approve'], 'rule_code' => $approval['rule_code'],
            ]);

            if ($approval['auto_approve']) {
                $this->approve($application, null, $application->review_notes, true);
            } else {
                Audit::notify($user->id, 'Pengajuan Pinjaman Diterima',
                    'Pengajuan pinjaman '.rupiah($amount).' sedang diproses pengurus. Nomor: '.$application->application_number.'.',
                    'info', 'loan_approval', route('loans.index', absolute: false));
                Audit::notifyAdmins('Pengajuan Pinjaman Baru',
                    'Pengajuan dari '.$user->full_name.' sebesar '.rupiah($amount).' perlu direview. Alasan: '.$approval['message'],
                    'warning', 'loan_approval', route('admin.loans.show', $application, absolute: false));
            }

            return $application->fresh();
        });
    }

    /**
     * Setujui pengajuan: buat pinjaman aktif, jadwal angsuran, dan transaksi pencairan.
     */
    public function approve(LoanApplication $application, ?User $admin, ?string $notes = null, bool $auto = false): ActiveLoan
    {
        return DB::transaction(function () use ($application, $admin, $notes, $auto) {
            $application = LoanApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();

            if (! $application->isReviewable()) {
                throw new RuntimeException('Pengajuan ini sudah diproses sebelumnya (status: '.(LoanApplication::STATUSES[$application->status] ?? $application->status).').');
            }
            if (ActiveLoan::where('user_id', $application->user_id)->where('status', 'active')->exists()) {
                throw new RuntimeException('Nasabah masih memiliki pinjaman aktif.');
            }

            $now = now();
            $amount = (float) $application->loan_amount;
            $term = (int) $application->loan_term_months;

            $loan = ActiveLoan::create([
                'user_id' => $application->user_id,
                'application_id' => $application->id,
                'loan_number' => Audit::reference('ACT', ActiveLoan::class, 'loan_number'),
                'original_amount' => $amount,
                'remaining_balance' => $amount,
                'monthly_installment' => $application->monthly_installment,
                'interest_rate' => $application->interest_rate,
                'loan_term_months' => $term,
                'remaining_months' => $term,
                'start_date' => $now->toDateString(),
                'next_due_date' => $now->copy()->addMonthNoOverflow()->toDateString(),
            ]);

            $this->createSchedule($loan);

            Transaction::create([
                'user_id' => $application->user_id,
                'reference_number' => Audit::reference('DIS', Transaction::class, 'reference_number'),
                'type' => 'loan_disbursement',
                'amount' => $amount,
                'description' => 'Pencairan pinjaman '.$loan->loan_number,
                'related_loan_id' => $loan->id,
                'payment_method' => 'transfer',
                'processed_by' => $admin?->id,
                'status' => 'completed',
                'transaction_date' => $now->toDateString(),
            ]);

            $application->update([
                'status' => 'disbursed',
                'auto_approved' => $auto,
                'reviewed_by' => $admin?->id,
                'reviewed_at' => $now,
                'approved_by' => $admin?->id,
                'approved_at' => $now,
                'disbursed_at' => $now,
                'review_notes' => $notes,
            ]);

            Audit::notify($application->user_id,
                $auto ? 'Pinjaman Disetujui Otomatis' : 'Pinjaman Disetujui',
                'Pinjaman '.rupiah($amount).' telah disetujui dan dicairkan. Angsuran pertama jatuh tempo '.tanggal($loan->next_due_date).'.',
                'success', 'loan_approval', route('loans.index', absolute: false));

            Audit::log($admin?->id ?? $application->user_id, $auto ? 'loan_auto_approved' : 'loan_approved', 'loan_application', $application->id,
                ['status' => 'pending'], ['status' => 'disbursed', 'loan_id' => $loan->id]);

            return $loan;
        });
    }

    public function reject(LoanApplication $application, User $admin, string $reason): void
    {
        DB::transaction(function () use ($application, $admin, $reason) {
            $application = LoanApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            if (! $application->isReviewable()) {
                throw new RuntimeException('Pengajuan ini sudah diproses sebelumnya.');
            }

            $application->update([
                'status' => 'rejected',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejected_reason' => $reason,
            ]);

            Audit::notify($application->user_id, 'Pinjaman Ditolak',
                'Pengajuan pinjaman '.rupiah($application->loan_amount).' ditolak. Alasan: '.$reason,
                'error', 'loan_approval', route('loans.index', absolute: false));
            Audit::log($admin->id, 'loan_rejected', 'loan_application', $application->id, ['status' => 'pending'], ['status' => 'rejected', 'reason' => $reason]);
        });
    }

    /** Jadwal bunga flat; pokok angsuran terakhir menyerap selisih pembulatan. */
    private function createSchedule(ActiveLoan $loan): void
    {
        $amount = (float) $loan->original_amount;
        $term = (int) $loan->loan_term_months;
        $principal = round($amount / $term, 2);
        $interest = round($amount * (float) $loan->interest_rate / 100, 2);

        for ($i = 1; $i <= $term; $i++) {
            $p = $i === $term ? round($amount - $principal * ($term - 1), 2) : $principal;
            $loan->schedules()->create([
                'installment_number' => $i,
                'due_date' => $loan->start_date->copy()->addMonthsNoOverflow($i)->toDateString(),
                'principal_amount' => $p,
                'interest_amount' => $interest,
                'total_amount' => round($p + $interest, 2),
            ]);
        }
    }

    public static function lateFee(LoanSchedule $schedule): float
    {
        $days = $schedule->daysLate();

        return $days > 0 ? round((float) $schedule->total_amount * Settings::float('late_fee_percentage') / 100 * $days) : 0;
    }

    /**
     * Catat pembayaran angsuran. Angsuran harus dibayar berurutan.
     * Bila dicatat nasabah, transaksi berstatus "pending" sampai diverifikasi pengurus;
     * bila dicatat pengurus ($processedBy), langsung dibukukan.
     */
    public function pay(User $member, LoanSchedule $schedule, string $method, ?User $processedBy = null): Transaction
    {
        return DB::transaction(function () use ($member, $schedule, $method, $processedBy) {
            $schedule = LoanSchedule::whereKey($schedule->id)->lockForUpdate()->firstOrFail();
            $loan = ActiveLoan::whereKey($schedule->loan_id)->lockForUpdate()->firstOrFail();

            if ($loan->user_id !== $member->id || $loan->status !== 'active') {
                throw new RuntimeException('Jadwal angsuran tidak ditemukan pada pinjaman aktif.');
            }
            if ($schedule->status === 'paid') {
                throw new RuntimeException('Angsuran ini sudah dibayar.');
            }
            if (Transaction::where('related_schedule_id', $schedule->id)->where('status', 'pending')->exists()) {
                throw new RuntimeException('Pembayaran angsuran ini sudah dikirim dan sedang menunggu verifikasi pengurus.');
            }
            $firstUnpaid = $loan->schedules()->unpaid()->orderBy('installment_number')->first();
            if ($firstUnpaid && $firstUnpaid->id !== $schedule->id) {
                throw new RuntimeException('Silakan bayar angsuran ke-'.$firstUnpaid->installment_number.' terlebih dahulu.');
            }

            $lateFee = self::lateFee($schedule);
            $total = round((float) $schedule->total_amount + $lateFee, 2);

            $transaction = Transaction::create([
                'user_id' => $member->id,
                'reference_number' => Audit::reference('PAY', Transaction::class, 'reference_number'),
                'type' => 'loan_payment',
                'amount' => $total,
                'description' => 'Angsuran ke-'.$schedule->installment_number.' '.$loan->loan_number.($lateFee > 0 ? ' (termasuk denda '.rupiah($lateFee).')' : ''),
                'related_loan_id' => $loan->id,
                'related_schedule_id' => $schedule->id,
                'payment_method' => $method,
                'status' => 'pending',
                'transaction_date' => today()->toDateString(),
            ]);

            if ($processedBy) {
                $this->confirmPayment($transaction, $processedBy);
            } else {
                Audit::notifyAdmins('Pembayaran Angsuran Perlu Verifikasi',
                    $member->full_name.' mengirim pembayaran '.rupiah($total).' untuk angsuran ke-'.$schedule->installment_number.'.',
                    'warning', 'payment_reminder', route('admin.verifications.index', absolute: false));
                Audit::log($member->id, 'loan_payment_submitted', 'transaction', $transaction->id, null, ['amount' => $total]);
            }

            return $transaction->fresh();
        });
    }

    /** Bukukan pembayaran angsuran yang sudah diverifikasi pengurus. */
    public function confirmPayment(Transaction $transaction, User $admin): void
    {
        DB::transaction(function () use ($transaction, $admin) {
            $transaction = Transaction::whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($transaction->type !== 'loan_payment' || $transaction->status !== 'pending') {
                throw new RuntimeException('Transaksi tidak ditemukan atau sudah diproses.');
            }

            $schedule = LoanSchedule::whereKey($transaction->related_schedule_id)->lockForUpdate()->firstOrFail();
            $loan = ActiveLoan::whereKey($schedule->loan_id)->lockForUpdate()->firstOrFail();
            if ($schedule->status === 'paid') {
                throw new RuntimeException('Angsuran ini sudah tercatat lunas.');
            }

            $lateFee = round((float) $transaction->amount - (float) $schedule->total_amount, 2);

            $transaction->update(['status' => 'completed', 'processed_by' => $admin->id, 'verified_at' => now()]);
            $schedule->update([
                'paid_amount' => $schedule->total_amount,
                'paid_date' => $transaction->transaction_date,
                'late_fee' => max(0, $lateFee),
                'status' => 'paid',
                'payment_transaction_id' => $transaction->id,
            ]);

            $next = $loan->schedules()->unpaid()->orderBy('installment_number')->first();
            $loan->remaining_balance = max(0, round((float) $loan->remaining_balance - (float) $schedule->principal_amount, 2));
            $loan->remaining_months = $loan->schedules()->unpaid()->count();
            $loan->total_paid = round((float) $loan->total_paid + (float) $transaction->amount, 2);
            $loan->late_fees = round((float) $loan->late_fees + max(0, $lateFee), 2);
            $loan->next_due_date = $next?->due_date;
            if (! $next) {
                $loan->status = 'completed';
                $loan->remaining_balance = 0;
            }
            $loan->save();

            Audit::notify($loan->user_id, 'Pembayaran Angsuran Diterima',
                'Pembayaran angsuran ke-'.$schedule->installment_number.' sebesar '.rupiah($transaction->amount).' telah diverifikasi.',
                'success', 'account_activity', route('loans.index', absolute: false));
            if (! $next) {
                Audit::notify($loan->user_id, 'Pinjaman Lunas', 'Selamat! Pinjaman '.$loan->loan_number.' telah lunas.', 'success', 'loan_approval');
            }
            Audit::log($admin->id, 'loan_payment_confirmed', 'transaction', $transaction->id, ['status' => 'pending'], ['status' => 'completed']);
        });
    }

    /**
     * Rincian pelunasan dipercepat:
     * angsuran yang sudah jatuh tempo + angsuran berjalan dibayar penuh (pokok + bunga + denda),
     * angsuran sesudahnya hanya pokok (bunga tidak ditagih).
     *
     * @return array{charged: \Illuminate\Support\Collection, waived: \Illuminate\Support\Collection, principal: float, interest: float, late_fee: float, total: float, interest_saved: float}
     */
    public function payoffQuote(ActiveLoan $loan): array
    {
        $unpaid = $loan->schedules()->unpaid()->orderBy('installment_number')->get();
        $firstNotDue = $unpaid->first(fn ($s) => $s->due_date->gte(today()));

        $charged = $unpaid->filter(fn ($s) => $s->due_date->lt(today()) || $s->id === $firstNotDue?->id)->values();
        $waived = $unpaid->reject(fn ($s) => $charged->contains('id', $s->id))->values();

        $principal = (float) $unpaid->sum('principal_amount');
        $interest = (float) $charged->sum('interest_amount');
        $lateFee = (float) $charged->sum(fn ($s) => self::lateFee($s));

        return [
            'charged' => $charged,
            'waived' => $waived,
            'principal' => round($principal, 2),
            'interest' => round($interest, 2),
            'late_fee' => round($lateFee, 2),
            'total' => round($principal + $interest + $lateFee, 2),
            'interest_saved' => round((float) $waived->sum('interest_amount'), 2),
        ];
    }

    /** Bukukan pelunasan dipercepat (dicatat pengurus setelah dana diterima). */
    public function payoff(ActiveLoan $loan, User $admin, string $method): Transaction
    {
        return DB::transaction(function () use ($loan, $admin, $method) {
            $loan = ActiveLoan::whereKey($loan->id)->lockForUpdate()->firstOrFail();
            if ($loan->status !== 'active') {
                throw new RuntimeException('Pinjaman ini sudah tidak aktif.');
            }
            if (Transaction::where('related_loan_id', $loan->id)->where('status', 'pending')->exists()) {
                throw new RuntimeException('Masih ada pembayaran angsuran yang menunggu verifikasi. Proses verifikasi terlebih dahulu.');
            }

            $quote = $this->payoffQuote($loan);
            if ($quote['charged']->isEmpty()) {
                throw new RuntimeException('Tidak ada angsuran yang tersisa.');
            }

            $transaction = Transaction::create([
                'user_id' => $loan->user_id,
                'reference_number' => Audit::reference('PAY', Transaction::class, 'reference_number'),
                'type' => 'loan_payment',
                'amount' => $quote['total'],
                'description' => 'Pelunasan dipercepat '.$loan->loan_number.' ('.($quote['charged']->count() + $quote['waived']->count()).' angsuran tersisa)',
                'related_loan_id' => $loan->id,
                'payment_method' => $method,
                'processed_by' => $admin->id,
                'status' => 'completed',
                'verified_at' => now(),
                'transaction_date' => today()->toDateString(),
            ]);

            foreach ($quote['charged'] as $s) {
                $fee = self::lateFee($s);
                $s->update(['paid_amount' => $s->total_amount, 'late_fee' => $fee, 'paid_date' => today(), 'status' => 'paid', 'payment_transaction_id' => $transaction->id]);
            }
            foreach ($quote['waived'] as $s) {
                // Bunga angsuran yang belum berjalan dihapus agar tidak dihitung sebagai pendapatan SHU.
                $s->update(['interest_amount' => 0, 'total_amount' => $s->principal_amount, 'paid_amount' => $s->principal_amount,
                    'paid_date' => today(), 'status' => 'paid', 'payment_transaction_id' => $transaction->id]);
            }

            $loan->update([
                'status' => 'completed',
                'remaining_balance' => 0,
                'remaining_months' => 0,
                'next_due_date' => null,
                'total_paid' => round((float) $loan->total_paid + $quote['total'], 2),
                'late_fees' => round((float) $loan->late_fees + $quote['late_fee'], 2),
            ]);

            Audit::notify($loan->user_id, 'Pinjaman Lunas (Pelunasan Dipercepat)',
                'Pinjaman '.$loan->loan_number.' telah dilunasi sebesar '.rupiah($quote['total']).'. Bunga yang tidak ditagih: '.rupiah($quote['interest_saved']).'.',
                'success', 'loan_approval', route('loans.index', absolute: false));
            Audit::log($admin->id, 'loan_early_payoff', 'active_loan', $loan->id, null, ['amount' => $quote['total'], 'interest_saved' => $quote['interest_saved']]);

            return $transaction;
        });
    }

    /** Tandai angsuran lewat jatuh tempo sebagai "overdue" (dijalankan terjadwal). */
    public function markOverdue(): int
    {
        return LoanSchedule::where('status', 'pending')->whereDate('due_date', '<', today())->update(['status' => 'overdue']);
    }
}
