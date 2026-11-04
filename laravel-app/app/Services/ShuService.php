<?php

namespace App\Services;

use App\Models\LoanSchedule;
use App\Models\ShuPeriod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Perhitungan SHU (Sisa Hasil Usaha).
 *   Pendapatan = bunga pinjaman terbayar + denda terbayar + pendapatan lain
 *   SHU        = Pendapatan - biaya operasional
 * Jasa anggota dibagi menjadi jasa simpanan (proporsional saldo simpanan wajib)
 * dan jasa pinjaman (proporsional bunga yang dibayar anggota).
 */
class ShuService
{
    public const POSTS = [
        'cadangan' => 'Cadangan Koperasi',
        'jasa_anggota' => 'Jasa Anggota',
        'dana_pengurus' => 'Dana Pengurus',
        'dana_karyawan' => 'Dana Karyawan',
        'dana_pendidikan' => 'Dana Pendidikan',
        'dana_sosial' => 'Dana Sosial',
    ];

    public function percentages(): array
    {
        $keys = array_merge(array_keys(self::POSTS), ['jasa_simpanan', 'jasa_pinjaman']);

        return collect($keys)->mapWithKeys(fn ($k) => [$k => Settings::float('shu_pct_'.$k)])->all();
    }

    public function calculate(int $year, float $operatingExpense = 0, float $otherIncome = 0): array
    {
        $start = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);
        $end = $year === (int) now()->year ? min(today()->toDateString(), $yearEnd) : $yearEnd;

        $operatingExpense = max(0, $operatingExpense);
        $otherIncome = max(0, $otherIncome);
        $pct = $this->percentages();

        $paid = LoanSchedule::query()
            ->join('active_loans', 'active_loans.id', '=', 'loan_schedules.loan_id')
            ->where('loan_schedules.status', 'paid')
            ->whereDate('loan_schedules.paid_date', '>=', $start)->whereDate('loan_schedules.paid_date', '<=', $end)
            ->groupBy('active_loans.user_id')
            ->select('active_loans.user_id', DB::raw('SUM(loan_schedules.interest_amount) as interest_paid'), DB::raw('SUM(loan_schedules.late_fee) as late_fee_paid'))
            ->get()
            ->keyBy('user_id');

        $interestIncome = (float) $paid->sum('interest_paid');
        $lateFeeIncome = (float) $paid->sum('late_fee_paid');
        $totalIncome = $interestIncome + $lateFeeIncome + $otherIncome;
        $shu = $totalIncome - $operatingExpense;

        $allocation = $this->allocate($shu, $pct);
        $members = $this->memberShares($paid, $allocation['jasa_simpanan'], $allocation['jasa_pinjaman']);

        return [
            'year' => $year,
            'start_date' => $start,
            'end_date' => $end,
            'operating_expense' => $operatingExpense,
            'other_income' => $otherIncome,
            'interest_income' => $interestIncome,
            'late_fee_income' => $lateFeeIncome,
            'total_income' => $totalIncome,
            'total_expense' => $operatingExpense,
            'shu_amount' => $shu,
            'percentages' => $pct,
            'allocation' => $allocation,
            'members' => $members['rows'],
            'total_savings_base' => $members['total_savings'],
            'total_interest_base' => $members['total_interest'],
            'member_count' => count($members['rows']),
            'member_shu_total' => array_sum(array_column($members['rows'], 'shu_total')),
            'saved' => ShuPeriod::with('generator')->where('year', $year)->first(),
        ];
    }

    private function allocate(float $shu, array $pct): array
    {
        $positive = max(0, $shu);
        $a = [];
        foreach (['cadangan', 'jasa_anggota', 'dana_pengurus', 'dana_karyawan', 'dana_pendidikan'] as $k) {
            $a[$k] = round($positive * $pct[$k] / 100);
        }
        // Pos terakhir menyerap sisa pembulatan agar total = SHU.
        $a['dana_sosial'] = $positive - array_sum($a);
        $a['jasa_simpanan'] = round($a['jasa_anggota'] * $pct['jasa_simpanan'] / 100);
        $a['jasa_pinjaman'] = $a['jasa_anggota'] - $a['jasa_simpanan'];

        return $a;
    }

    private function memberShares($paid, float $poolSimpanan, float $poolPinjaman): array
    {
        $members = User::query()
            ->leftJoin('savings_accounts', fn ($j) => $j->on('savings_accounts.user_id', '=', 'users.id')->where('savings_accounts.status', 'active'))
            ->leftJoin('divisions', 'divisions.id', '=', 'users.division_id')
            ->where('users.role', 'nasabah')
            ->whereIn('users.status', ['active', 'resigned'])
            ->orderBy('users.full_name')
            ->get(['users.id as user_id', 'users.nik', 'users.full_name', 'divisions.name as division_name', DB::raw('COALESCE(savings_accounts.balance, 0) as savings_balance')]);

        $totalSavings = (float) $members->sum('savings_balance');
        $totalInterest = (float) $paid->sum('interest_paid');

        $rows = [];
        foreach ($members as $m) {
            $savings = (float) $m->savings_balance;
            $interest = (float) ($paid[$m->user_id]->interest_paid ?? 0);
            $lateFee = (float) ($paid[$m->user_id]->late_fee_paid ?? 0);
            if ($savings <= 0 && $interest <= 0 && $lateFee <= 0) {
                continue;
            }

            $s = ($totalSavings > 0 && $poolSimpanan > 0) ? floor($savings / $totalSavings * $poolSimpanan) : 0;
            $p = ($totalInterest > 0 && $poolPinjaman > 0) ? floor($interest / $totalInterest * $poolPinjaman) : 0;

            $rows[] = [
                'user_id' => (int) $m->user_id,
                'nik' => $m->nik,
                'full_name' => $m->full_name,
                'division_name' => $m->division_name,
                'savings_balance' => $savings,
                'interest_paid' => $interest,
                'late_fee_paid' => $lateFee,
                'shu_simpanan' => $s,
                'shu_pinjaman' => $p,
                'shu_total' => $s + $p,
            ];
        }

        if ($rows !== []) {
            $last = count($rows) - 1;
            if ($totalSavings > 0) {
                $rows[$last]['shu_simpanan'] += $poolSimpanan - array_sum(array_column($rows, 'shu_simpanan'));
            }
            if ($totalInterest > 0) {
                $rows[$last]['shu_pinjaman'] += $poolPinjaman - array_sum(array_column($rows, 'shu_pinjaman'));
            }
            $rows[$last]['shu_total'] = $rows[$last]['shu_simpanan'] + $rows[$last]['shu_pinjaman'];
        }

        usort($rows, fn ($a, $b) => [$b['shu_total'], $a['full_name']] <=> [$a['shu_total'], $b['full_name']]);

        return ['rows' => $rows, 'total_savings' => $totalSavings, 'total_interest' => $totalInterest];
    }

    public function save(array $calc, User $admin, string $status, ?string $notes = null): ShuPeriod
    {
        $existing = ShuPeriod::where('year', $calc['year'])->first();
        if ($existing && $existing->status === 'final') {
            throw new RuntimeException('Laporan SHU tahun ini sudah dikunci. Buka kunci dulu jika perlu dihitung ulang.');
        }

        return DB::transaction(function () use ($calc, $admin, $status, $notes, $existing) {
            $fields = [
                'year' => $calc['year'],
                'start_date' => $calc['start_date'],
                'end_date' => $calc['end_date'],
                'total_savings_base' => $calc['total_savings_base'],
                'total_interest_base' => $calc['total_interest_base'],
                'member_count' => $calc['member_count'],
                'notes' => $notes,
                'status' => $status,
                'generated_by' => $admin->id,
            ];
            foreach (['operating_expense', 'other_income', 'interest_income', 'late_fee_income', 'total_income', 'total_expense', 'shu_amount'] as $k) {
                $fields[$k] = $calc[$k];
            }
            foreach ($calc['percentages'] as $k => $v) {
                $fields['pct_'.$k] = $v;
                $fields['amt_'.$k] = $calc['allocation'][$k];
            }

            $period = $existing ?? new ShuPeriod;
            $period->fill($fields)->save();
            $period->shares()->delete();

            foreach ($calc['members'] as $row) {
                $period->shares()->create(collect($row)->only(['user_id', 'savings_balance', 'interest_paid', 'late_fee_paid', 'shu_simpanan', 'shu_pinjaman', 'shu_total'])->all());
            }

            Audit::log($admin->id, $status === 'final' ? 'shu_finalized' : 'shu_saved', 'shu_period', $period->id, null, ['year' => $calc['year'], 'shu' => $calc['shu_amount']]);

            return $period;
        });
    }

    public function reopen(int $year, User $admin): void
    {
        $period = ShuPeriod::where('year', $year)->first();
        if (! $period) {
            throw new RuntimeException('Laporan SHU tahun ini belum ada.');
        }
        $period->update(['status' => 'draft']);
        Audit::log($admin->id, 'shu_reopened', 'shu_period', $period->id, null, ['year' => $year]);
    }

    /**
     * Bagian SHU satu anggota. Bila laporan sudah final, angka diambil dari laporan
     * yang dikunci (bukan dihitung ulang), agar sama dengan yang dibagikan.
     */
    public function memberShare(User $user, int $year): array
    {
        $period = ShuPeriod::where('year', $year)->first();
        if ($period && $period->status === 'final') {
            $share = $period->shares()->where('user_id', $user->id)->first();

            return [
                'source' => 'final',
                'period' => $period,
                'row' => $share?->toArray(),
                'shu_amount' => (float) $period->shu_amount,
                'pool_simpanan' => (float) $period->amt_jasa_simpanan,
                'pool_pinjaman' => (float) $period->amt_jasa_pinjaman,
            ];
        }

        $savedExpense = (float) ($period->operating_expense ?? 0);
        $savedIncome = (float) ($period->other_income ?? 0);
        $calc = $this->calculate($year, $savedExpense, $savedIncome);
        $row = collect($calc['members'])->firstWhere('user_id', $user->id);

        return [
            'source' => 'estimate',
            'period' => $period,
            'row' => $row,
            'shu_amount' => $calc['shu_amount'],
            'pool_simpanan' => $calc['allocation']['jasa_simpanan'],
            'pool_pinjaman' => $calc['allocation']['jasa_pinjaman'],
        ];
    }
}
