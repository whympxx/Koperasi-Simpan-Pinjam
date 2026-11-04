<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit;
use App\Services\Settings;
use App\Services\ShuService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $keys = array_keys(config('koperasi.defaults'));

        return view('admin.settings', [
            'values' => collect($keys)->mapWithKeys(fn ($k) => [$k => Settings::get($k)])->all(),
            'posts' => ShuService::POSTS,
        ]);
    }

    public function update(Request $request, string $section)
    {
        $uid = $request->user()->id;

        if ($section === 'general') {
            $request->merge(['min_mandatory_savings' => parse_money($request->input('min_mandatory_savings')),
                'min_loan_amount' => parse_money($request->input('min_loan_amount')),
                'monthly_mandatory_savings' => parse_money($request->input('monthly_mandatory_savings'))]);
            $data = $request->validate([
                'loan_interest_rate' => ['required', 'numeric', 'min:0', 'max:10'],
                'max_loan_term' => ['required', 'integer', 'min:1', 'max:18'],
                'max_loan_term_standard' => ['required', 'integer', 'min:1', 'lte:max_loan_term'],
                'max_loan_multiplier' => ['required', 'numeric', 'min:1'],
                'max_loan_multiplier_limited' => ['required', 'numeric', 'min:1'],
                'min_mandatory_savings' => ['required', 'numeric', 'min:0'],
                'monthly_mandatory_savings' => ['required', 'numeric', 'min:0'],
                'min_loan_amount' => ['required', 'numeric', 'min:0'],
                'min_employment_months' => ['required', 'integer', 'min:0', 'max:120'],
                'late_fee_percentage' => ['required', 'numeric', 'min:0', 'max:10'],
            ]);
            foreach ($data as $k => $v) {
                Settings::set($k, $v, 'number', null, $uid);
            }
        } elseif ($section === 'auto_approve') {
            $request->merge(['loan_auto_approve_max_amount' => parse_money($request->input('loan_auto_approve_max_amount'))]);
            $data = $request->validate([
                'loan_auto_approve_max_amount' => ['required', 'numeric', 'gt:0'],
                'loan_auto_approve_max_term_months' => ['required', 'integer', 'min:1', 'max:18'],
            ]);
            Settings::set('loan_auto_approve_enabled', $request->boolean('loan_auto_approve_enabled'), 'boolean', null, $uid);
            foreach ($data as $k => $v) {
                Settings::set($k, $v, 'number', null, $uid);
            }
            $data['loan_auto_approve_enabled'] = $request->boolean('loan_auto_approve_enabled');
        } else {
            $rules = [];
            foreach (array_merge(array_keys(ShuService::POSTS), ['jasa_simpanan', 'jasa_pinjaman']) as $k) {
                $rules['shu_pct_'.$k] = ['required', 'numeric', 'min:0', 'max:100'];
            }
            $data = $request->validate($rules);
            $main = collect(array_keys(ShuService::POSTS))->sum(fn ($k) => (float) $data['shu_pct_'.$k]);
            if (abs($main - 100) > 0.01) {
                return back()->withInput()->with('error', 'Total alokasi SHU harus 100%. Saat ini '.number_format($main, 2, ',', '.').'%.');
            }
            if (abs($data['shu_pct_jasa_simpanan'] + $data['shu_pct_jasa_pinjaman'] - 100) > 0.01) {
                return back()->withInput()->with('error', 'Porsi jasa simpanan + jasa pinjaman harus 100%.');
            }
            foreach ($data as $k => $v) {
                Settings::set($k, $v, 'number', null, $uid);
            }
        }

        Audit::log($uid, 'settings_updated', 'system_setting', null, null, ['section' => $section] + $data);

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
