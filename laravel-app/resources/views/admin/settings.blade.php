@extends('layouts.app')
@section('title', 'Pengaturan Sistem')
@section('content')
@php $v = fn ($k) => old($k, $values[$k]); $num = fn ($x) => rtrim(rtrim(number_format((float) $x, 2, '.', ''), '0'), '.'); @endphp
<div class="row g-4">
    <div class="col-lg-7">
        <form class="card" method="POST" action="{{ route('admin.settings.update', 'general') }}">
            @csrf @method('PUT')
            <div class="card-header bg-white fw-semibold">Ketentuan Pinjaman & Simpanan (RAT 2025)</div>
            <div class="card-body row g-3">
                <div class="col-md-6"><label class="form-label">Bunga pinjaman (% / bulan, flat)</label><input type="number" step="0.01" name="loan_interest_rate" value="{{ $num($v('loan_interest_rate')) }}" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Denda keterlambatan (% / hari)</label><input type="number" step="0.01" name="late_fee_percentage" value="{{ $num($v('late_fee_percentage')) }}" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Plafon standar (× simpanan, potongan HRD)</label><input type="number" step="0.1" name="max_loan_multiplier" value="{{ $num($v('max_loan_multiplier')) }}" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Plafon terbatas (× simpanan, outsourcing)</label><input type="number" step="0.1" name="max_loan_multiplier_limited" value="{{ $num($v('max_loan_multiplier_limited')) }}" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Tenor standar (bulan)</label><input type="number" name="max_loan_term_standard" value="{{ (int) $v('max_loan_term_standard') }}" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Tenor kebijakan khusus (bulan, maks 18)</label><input type="number" name="max_loan_term" value="{{ (int) $v('max_loan_term') }}" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Minimal simpanan wajib (Rp)</label><input type="text" name="min_mandatory_savings" value="{{ (int) $v('min_mandatory_savings') }}" class="form-control" data-money required></div>
                <div class="col-md-6"><label class="form-label">Iuran simpanan wajib / bulan (Rp)</label><input type="text" name="monthly_mandatory_savings" value="{{ (int) $v('monthly_mandatory_savings') }}" class="form-control" data-money required></div>
                <div class="col-md-6"><label class="form-label">Minimal pengajuan pinjaman (Rp)</label><input type="text" name="min_loan_amount" value="{{ (int) $v('min_loan_amount') }}" class="form-control" data-money required></div>
                <div class="col-md-6"><label class="form-label">Minimal masa kerja (bulan)</label><input type="number" name="min_employment_months" value="{{ (int) $v('min_employment_months') }}" class="form-control" required></div>
            </div>
            <div class="card-footer bg-white"><button class="btn btn-primary"><i class="fas fa-save me-1"></i>Simpan</button></div>
        </form>
    </div>
    <div class="col-lg-5">
        <form class="card mb-4" method="POST" action="{{ route('admin.settings.update', 'auto_approve') }}">
            @csrf @method('PUT')
            <div class="card-header bg-white fw-semibold">Persetujuan Otomatis</div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="loan_auto_approve_enabled" value="1" id="aa" @checked($values['loan_auto_approve_enabled'])>
                    <label class="form-check-label" for="aa">Aktifkan auto-approve</label>
                </div>
                <label class="form-label">Batas jumlah (Rp)</label>
                <input type="text" name="loan_auto_approve_max_amount" value="{{ (int) $v('loan_auto_approve_max_amount') }}" class="form-control mb-3" data-money required>
                <label class="form-label">Batas tenor (bulan)</label>
                <input type="number" name="loan_auto_approve_max_term_months" value="{{ (int) $v('loan_auto_approve_max_term_months') }}" class="form-control" required>
                <div class="form-text">Kriteria lain (outsourcing, riwayat tunggakan, tenor khusus) diatur di menu Basis Aturan.</div>
            </div>
            <div class="card-footer bg-white"><button class="btn btn-primary"><i class="fas fa-save me-1"></i>Simpan</button></div>
        </form>
        <form class="card" method="POST" action="{{ route('admin.settings.update', 'shu') }}">
            @csrf @method('PUT')
            <div class="card-header bg-white fw-semibold">Alokasi SHU (%) — total harus 100</div>
            <div class="card-body row g-2">
                @foreach ($posts as $k => $label)
                    <div class="col-7 small align-self-center">{{ $label }}</div>
                    <div class="col-5"><input type="number" step="0.01" name="shu_pct_{{ $k }}" value="{{ $num($v('shu_pct_'.$k)) }}" class="form-control form-control-sm" required></div>
                @endforeach
                <div class="col-12"><hr class="my-1"><small class="text-muted">Pembagian jasa anggota (total 100):</small></div>
                <div class="col-7 small align-self-center">Jasa simpanan</div><div class="col-5"><input type="number" step="0.01" name="shu_pct_jasa_simpanan" value="{{ $num($v('shu_pct_jasa_simpanan')) }}" class="form-control form-control-sm" required></div>
                <div class="col-7 small align-self-center">Jasa pinjaman</div><div class="col-5"><input type="number" step="0.01" name="shu_pct_jasa_pinjaman" value="{{ $num($v('shu_pct_jasa_pinjaman')) }}" class="form-control form-control-sm" required></div>
            </div>
            <div class="card-footer bg-white"><button class="btn btn-primary"><i class="fas fa-save me-1"></i>Simpan</button></div>
        </form>
    </div>
</div>
@endsection
