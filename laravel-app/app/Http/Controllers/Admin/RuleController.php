<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rule;
use App\Models\RuleEvaluationLog;
use App\Services\Audit;
use App\Services\LoanService;
use App\Services\RuleEngine;
use App\Services\Settings;
use Illuminate\Http\Request;
use InvalidArgumentException;

class RuleController extends Controller
{
    public function index(RuleEngine $engine)
    {
        return $this->render($engine, null);
    }

    public function edit(Rule $rule, RuleEngine $engine)
    {
        return $this->render($engine, $rule->load('conditions'));
    }

    private function render(RuleEngine $engine, ?Rule $editing)
    {
        return view('admin.rules', [
            'rules' => Rule::with('conditions')->orderBy('rule_group')->orderBy('priority')->get()->groupBy('rule_group'),
            'editing' => $editing,
            'nextCode' => $engine->nextRuleCode(),
            'logs' => RuleEvaluationLog::with('user')->latest()->limit(20)->get(),
            'simulation' => session('simulation'),
        ]);
    }

    public function store(Request $request, RuleEngine $engine)
    {
        return $this->persist($request, $engine, null);
    }

    public function update(Request $request, Rule $rule, RuleEngine $engine)
    {
        return $this->persist($request, $engine, $rule);
    }

    private function persist(Request $request, RuleEngine $engine, ?Rule $rule)
    {
        $data = $request->only(['rule_group', 'name', 'description', 'conclusion', 'message', 'priority']);
        $data['rule_code'] = strtoupper(trim((string) $request->input('rule_code')));
        $data['is_active'] = $request->boolean('is_active');

        $conditions = [];
        foreach ((array) $request->input('fact_key', []) as $i => $fact) {
            if ($fact === '' || $fact === null) {
                continue;
            }
            $conditions[] = [
                'fact_key' => $fact,
                'operator' => $request->input("operator.$i", '='),
                'value_type' => $request->input("value_type.$i", 'static'),
                'compare_value' => trim((string) $request->input("compare_value.$i", '')),
                'fallback_value' => trim((string) $request->input("fallback_value.$i", '')),
            ];
        }

        try {
            $saved = $engine->save($data, $conditions, $request->user()->id, $rule);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        Audit::log($request->user()->id, 'rule_saved', 'rule', $saved->id, null, ['rule_code' => $saved->rule_code]);

        return redirect()->route('admin.rules.index')->with('success', 'Aturan '.$saved->rule_code.' berhasil disimpan.');
    }

    public function toggle(Request $request, Rule $rule)
    {
        $rule->update(['is_active' => ! $rule->is_active, 'updated_by' => $request->user()->id]);
        Audit::log($request->user()->id, 'rule_status_changed', 'rule', $rule->id, null, ['is_active' => $rule->is_active]);

        return back()->with('success', 'Aturan '.$rule->rule_code.($rule->is_active ? ' diaktifkan.' : ' dinonaktifkan.'));
    }

    public function destroy(Request $request, Rule $rule)
    {
        if ($rule->is_system) {
            return back()->with('error', 'Aturan inti sistem tidak dapat dihapus. Nonaktifkan saja bila tidak dipakai.');
        }
        Audit::log($request->user()->id, 'rule_deleted', 'rule', $rule->id, ['rule_code' => $rule->rule_code]);
        $rule->delete();

        return back()->with('success', 'Aturan dihapus.');
    }

    /** Simulasi keputusan dengan fakta yang diisi manual (tidak menyimpan pengajuan). */
    public function simulate(Request $request, RuleEngine $engine)
    {
        $in = $request->validate([
            'masa_kerja_bulan' => ['required', 'integer', 'min:0'],
            'status_karyawan' => ['required', 'in:active,inactive,resigned'],
            'kategori_anggota' => ['required', 'in:karyawan,driver,outsourcing'],
            'potongan_hrd' => ['required', 'in:0,1'],
            'gaji_bulanan' => ['nullable'],
            'simpanan_wajib' => ['nullable'],
            'punya_pinjaman_aktif' => ['required', 'in:0,1'],
            'punya_pengajuan_pending' => ['required', 'in:0,1'],
            'riwayat_tunggakan' => ['required', 'integer', 'min:0'],
            'jumlah_pinjaman' => ['nullable'],
            'tenor_bulan' => ['required', 'integer', 'min:1'],
        ]);

        $payroll = $in['kategori_anggota'] !== 'outsourcing' && $in['potongan_hrd'] === '1';
        $multiplier = $payroll ? Settings::float('max_loan_multiplier') : Settings::float('max_loan_multiplier_limited');
        $savings = parse_money($in['simpanan_wajib'] ?? 0);
        $salary = parse_money($in['gaji_bulanan'] ?? 0);
        $amount = parse_money($in['jumlah_pinjaman'] ?? 0);
        $term = (int) $in['tenor_bulan'];
        $installment = LoanService::installment($amount, Settings::float('loan_interest_rate'), $term);

        $facts = [
            'masa_kerja_bulan' => (int) $in['masa_kerja_bulan'],
            'status_karyawan' => $in['status_karyawan'],
            'kategori_anggota' => $in['kategori_anggota'],
            'potongan_hrd' => $payroll ? 1 : 0,
            'gaji_bulanan' => $salary,
            'simpanan_wajib' => $savings,
            'batas_kelipatan' => $multiplier,
            'plafon_maksimal' => floor($savings * $multiplier),
            'punya_pinjaman_aktif' => (int) $in['punya_pinjaman_aktif'],
            'punya_pengajuan_pending' => (int) $in['punya_pengajuan_pending'],
            'riwayat_tunggakan' => (int) $in['riwayat_tunggakan'],
            'pinjaman_lunas' => 0,
            'auto_approve_aktif' => Settings::bool('loan_auto_approve_enabled') ? 1 : 0,
            'jumlah_pinjaman' => $amount,
            'tenor_bulan' => $term,
            'angsuran_bulanan' => $installment,
            'kelipatan_simpanan' => $savings > 0 ? round($amount / $savings, 2) : 999,
            'kelipatan_gaji' => $salary > 0 ? round($amount / $salary, 2) : 0,
            'rasio_angsuran_gaji' => $salary > 0 ? round($installment / $salary * 100, 2) : 0,
        ];

        $eligibility = $engine->evaluate(RuleEngine::GROUP_ELIGIBILITY, $facts, 'eligible', 'Memenuhi seluruh syarat pengajuan.');
        $decision = $eligibility['decision'] === 'reject' ? null
            : $engine->evaluate(RuleEngine::GROUP_DECISION, $facts, 'manual_review', 'Tidak ada aturan persetujuan otomatis yang terpenuhi.');

        return redirect()->route('admin.rules.index')->withInput()
            ->with('simulation', compact('facts', 'eligibility', 'decision'));
    }
}
