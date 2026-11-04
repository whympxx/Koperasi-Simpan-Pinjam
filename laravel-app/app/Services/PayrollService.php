<?php

namespace App\Services;

use App\Models\LoanSchedule;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Potong gaji massal: setiap bulan HRD membukukan iuran simpanan wajib
 * dan angsuran pinjaman seluruh anggota yang angsurannya dipotong gaji.
 */
class PayrollService
{
    public function __construct(private SavingsService $savings, private LoanService $loans)
    {
    }

    public static function depositLabel(string $period): string
    {
        return 'Iuran simpanan wajib periode '.$period.' (potong gaji)';
    }

    /** Daftar anggota dan potongan untuk periode "Y-m". */
    public function preview(string $period): Collection
    {
        $end = Carbon::createFromFormat('Y-m', $period)->endOfMonth();
        $iuran = Settings::float('monthly_mandatory_savings');

        return User::with(['activeLoan', 'division'])
            ->where('role', 'nasabah')->where('status', 'active')
            ->where('payroll_deduction', true)->where('member_category', '!=', 'outsourcing')
            ->orderBy('full_name')->get()
            ->map(function (User $u) use ($period, $end, $iuran) {
                $depositDone = Transaction::where('user_id', $u->id)->where('type', 'savings_deposit')
                    ->where('description', self::depositLabel($period))->whereIn('status', ['completed', 'pending'])->exists();

                $schedule = $u->activeLoan
                    ? LoanSchedule::where('loan_id', $u->activeLoan->id)->unpaid()
                        ->whereDate('due_date', '<=', $end)->orderBy('installment_number')->first()
                    : null;
                $schedulePending = $schedule && Transaction::where('related_schedule_id', $schedule->id)->where('status', 'pending')->exists();
                $installment = $schedule && ! $schedulePending ? (float) $schedule->total_amount + LoanService::lateFee($schedule) : 0;

                return [
                    'user' => $u,
                    'deposit' => $depositDone ? 0 : $iuran,
                    'deposit_done' => $depositDone,
                    'schedule' => $schedulePending ? null : $schedule,
                    'installment' => $installment,
                    'total' => ($depositDone ? 0 : $iuran) + $installment,
                ];
            });
    }

    /**
     * @param  int[]  $userIds
     * @return array{ok: int, failed: array<string>, amount: float}
     */
    public function process(string $period, User $admin, array $userIds): array
    {
        $result = ['ok' => 0, 'failed' => [], 'amount' => 0.0];

        foreach ($this->preview($period)->whereIn('user.id', $userIds) as $row) {
            $member = $row['user'];
            try {
                if ($row['deposit'] > 0) {
                    $this->savings->deposit($member, $row['deposit'], 'payroll_deduction', self::depositLabel($period), $admin);
                    $result['amount'] += $row['deposit'];
                }
                if ($row['schedule']) {
                    $this->loans->pay($member, $row['schedule'], 'payroll_deduction', $admin);
                    $result['amount'] += $row['installment'];
                }
                if ($row['total'] > 0) {
                    $result['ok']++;
                }
            } catch (Throwable $e) {
                $result['failed'][] = $member->full_name.': '.$e->getMessage();
            }
        }

        Audit::log($admin->id, 'payroll_batch_processed', null, null, null, ['period' => $period] + $result);

        return $result;
    }
}
