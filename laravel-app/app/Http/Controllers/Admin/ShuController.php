<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShuPeriod;
use App\Services\ShuService;
use Illuminate\Http\Request;
use RuntimeException;

class ShuController extends Controller
{
    public function index(Request $request, ShuService $shu)
    {
        $year = $this->year($request);
        $saved = ShuPeriod::where('year', $year)->first();

        // Laporan final ditampilkan dari data tersimpan, bukan dihitung ulang.
        $expense = $request->has('operating_expense') ? parse_money($request->query('operating_expense')) : (float) ($saved->operating_expense ?? 0);
        $other = $request->has('other_income') ? parse_money($request->query('other_income')) : (float) ($saved->other_income ?? 0);

        return view('admin.shu', [
            'year' => $year,
            'calc' => $saved?->status === 'final' ? null : $shu->calculate($year, $expense, $other),
            'saved' => $saved?->load('generator'),
            'shares' => $saved?->status === 'final' ? $saved->shares()->with('user.division')->orderByDesc('shu_total')->get() : collect(),
            'expense' => $expense,
            'other' => $other,
            'periods' => ShuPeriod::orderByDesc('year')->get(),
            'posts' => ShuService::POSTS,
        ]);
    }

    public function save(Request $request, ShuService $shu)
    {
        $request->merge([
            'operating_expense' => parse_money($request->input('operating_expense')),
            'other_income' => parse_money($request->input('other_income')),
        ]);
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:'.now()->year],
            'operating_expense' => ['required', 'numeric', 'min:0'],
            'other_income' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'action' => ['required', 'in:draft,final'],
        ]);

        try {
            $calc = $shu->calculate((int) $data['year'], (float) $data['operating_expense'], (float) $data['other_income']);
            $shu->save($calc, $request->user(), $data['action'], $data['notes'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.shu.index', ['year' => $data['year']])
            ->with('success', $data['action'] === 'final' ? 'Laporan SHU dikunci sebagai final.' : 'Perhitungan SHU disimpan sebagai draf.');
    }

    public function reopen(Request $request, int $year, ShuService $shu)
    {
        abort_unless($request->user()->isSuperAdmin(), 403, 'Hanya super admin yang dapat membuka kunci laporan SHU.');

        try {
            $shu->reopen($year, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.shu.index', ['year' => $year])->with('success', 'Laporan SHU dibuka kembali sebagai draf.');
    }

    public function export(int $year, ShuService $shu)
    {
        $saved = ShuPeriod::where('year', $year)->first();
        if ($saved?->status === 'final') {
            $rows = $saved->shares()->with('user.division')->orderByDesc('shu_total')->get()->map(fn ($s) => [
                'nik' => $s->user->nik, 'full_name' => $s->user->full_name, 'division_name' => $s->user->division?->name,
            ] + $s->toArray())->all();
            $shuAmount = $saved->shu_amount;
        } else {
            $calc = $shu->calculate($year, (float) ($saved->operating_expense ?? 0), (float) ($saved->other_income ?? 0));
            $rows = $calc['members'];
            $shuAmount = $calc['shu_amount'];
        }

        return response()->streamDownload(function () use ($rows, $year, $shuAmount, $saved) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Laporan SHU '.config('koperasi.name').' Tahun Buku '.$year.' ('.($saved?->status === 'final' ? 'FINAL' : 'DRAF/ESTIMASI').')'], ';');
            fputcsv($out, ['Total SHU', $shuAmount], ';');
            fputcsv($out, [], ';');
            fputcsv($out, ['NIK', 'Nama', 'Divisi', 'Simpanan Wajib', 'Bunga Dibayar', 'Denda', 'SHU Jasa Simpanan', 'SHU Jasa Pinjaman', 'Total SHU'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [$r['nik'], $r['full_name'], $r['division_name'] ?? '', $r['savings_balance'], $r['interest_paid'], $r['late_fee_paid'], $r['shu_simpanan'], $r['shu_pinjaman'], $r['shu_total']], ';');
            }
            fclose($out);
        }, 'laporan-shu-'.$year.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function year(Request $request): int
    {
        $year = (int) $request->query('year', now()->year);

        return ($year < 2000 || $year > (int) now()->year) ? (int) now()->year : $year;
    }
}
