<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanSchedule;
use App\Services\Audit;
use App\Services\LoanService;
use Illuminate\Http\Request;

class OverdueController extends Controller
{
    public function index()
    {
        $schedules = LoanSchedule::overdue()->with('loan.user')->orderBy('due_date')->paginate(20);

        return view('admin.overdue', [
            'schedules' => $schedules,
            'totalAmount' => LoanSchedule::overdue()->sum('total_amount'),
        ]);
    }

    public function remind(Request $request, LoanSchedule $schedule)
    {
        abort_if($schedule->status === 'paid', 404);
        $schedule->load('loan.user');
        $member = $schedule->loan->user;

        $message = 'Angsuran ke-'.$schedule->installment_number.' pinjaman '.$schedule->loan->loan_number
            .' sebesar '.rupiah($schedule->total_amount).' jatuh tempo '.tanggal($schedule->due_date).'.';
        if ($schedule->isOverdue()) {
            $message .= ' Pembayaran sudah terlambat '.$schedule->daysLate().' hari; denda saat ini '.rupiah(LoanService::lateFee($schedule)).'.';
        }
        $message .= ' Mohon segera lakukan pembayaran.';

        Audit::notify($member->id, 'Pengingat Pembayaran Angsuran', $message, 'warning', 'payment_reminder', route('loans.payment', absolute: false));
        $schedule->update(['reminder_sent_at' => now()]);
        Audit::log($request->user()->id, 'payment_reminder_sent', 'loan_schedule', $schedule->id);

        return back()->with('success', 'Pengingat dikirim ke '.$member->full_name.'.');
    }
}
