<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PayrollService;
use App\Services\Settings;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function index(Request $request, PayrollService $payroll)
    {
        $period = $this->period($request->query('period'));

        $rows = $payroll->preview($period);

        return view('admin.payroll', [
            'period' => $period,
            'rows' => $rows,
            'iuran' => Settings::float('monthly_mandatory_savings'),
            'grandTotal' => $rows->sum('total'),
        ]);
    }

    public function process(Request $request, PayrollService $payroll)
    {
        $data = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'users' => ['required', 'array', 'min:1'],
            'users.*' => ['integer'],
        ], ['users.required' => 'Pilih minimal satu anggota.']);

        $period = $this->period($data['period']);
        $result = $payroll->process($period, $request->user(), array_map('intval', $data['users']));

        $message = $result['ok'].' anggota dibukukan, total '.rupiah($result['amount']).'.';
        if ($result['failed']) {
            return redirect()->route('admin.payroll.index', ['period' => $period])
                ->with('success', $message)->with('error', 'Gagal: '.implode('; ', $result['failed']));
        }

        return redirect()->route('admin.payroll.index', ['period' => $period])->with('success', $message);
    }

    /** Periode tidak boleh di masa depan. */
    private function period(?string $value): string
    {
        $current = now()->format('Y-m');
        if (! $value || ! preg_match('/^\d{4}-\d{2}$/', $value) || $value > $current) {
            return $current;
        }

        return $value;
    }
}
