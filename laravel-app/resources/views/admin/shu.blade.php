@extends('layouts.app')
@section('title', 'Perhitungan SHU')
@section('content')
@php
    $isFinal = $saved?->status === 'final';
    // Sumber data: laporan final tersimpan, atau perhitungan langsung.
    $src = $isFinal ? $saved->toArray() : $calc;
    $alloc = $isFinal ? collect($posts)->keys()->merge(['jasa_simpanan', 'jasa_pinjaman'])->mapWithKeys(fn ($k) => [$k => $saved->{'amt_'.$k}])->all() : $calc['allocation'];
    $pct = $isFinal ? collect($posts)->keys()->merge(['jasa_simpanan', 'jasa_pinjaman'])->mapWithKeys(fn ($k) => [$k => $saved->{'pct_'.$k}])->all() : $calc['percentages'];
    $rows = $isFinal ? $shares->map(fn ($s) => ['nik' => $s->user->nik, 'full_name' => $s->user->full_name, 'division_name' => $s->user->division?->name] + $s->toArray())->all() : $calc['members'];
@endphp
<div class="d-flex flex-wrap gap-2 align-items-center mb-3 no-print">
    <form class="d-flex gap-2">
        <select name="year" class="form-select" onchange="this.form.submit()">
            @for ($y = now()->year; $y >= now()->year - 5; $y--)<option value="{{ $y }}" @selected($y === $year)>Tahun Buku {{ $y }}</option>@endfor
        </select>
    </form>
    @if ($saved){!! status_badge($saved->status) !!} <small class="text-muted">disimpan {{ tanggal($saved->updated_at, true) }} oleh {{ $saved->generator?->full_name }}</small>@else<span class="badge bg-light text-dark">Belum disimpan</span>@endif
    <div class="ms-auto d-flex gap-2">
        <a href="{{ route('admin.shu.export', $year) }}" class="btn btn-outline-success"><i class="fas fa-file-csv me-1"></i>CSV</a>
        <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-1"></i>Cetak</button>
        @if ($isFinal && auth()->user()->isSuperAdmin())
            <form method="POST" action="{{ route('admin.shu.reopen', $year) }}" onsubmit="return confirm('Buka kunci laporan SHU {{ $year }}?')">@csrf<button class="btn btn-outline-warning"><i class="fas fa-lock-open me-1"></i>Buka Kunci</button></form>
        @endif
    </div>
</div>

@unless ($isFinal)
    <form class="card card-body mb-4 no-print" method="POST" action="{{ route('admin.shu.save') }}">
        @csrf
        <input type="hidden" name="year" value="{{ $year }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label small">Biaya Operasional</label><input type="text" name="operating_expense" value="{{ (int) $expense }}" class="form-control" data-money></div>
            <div class="col-md-3"><label class="form-label small">Pendapatan Lain</label><input type="text" name="other_income" value="{{ (int) $other }}" class="form-control" data-money></div>
            <div class="col-md-3"><label class="form-label small">Catatan</label><input type="text" name="notes" value="{{ $saved?->notes }}" class="form-control"></div>
            <div class="col-md-3 d-flex gap-2">
                <button formmethod="GET" formaction="{{ route('admin.shu.index') }}" class="btn btn-outline-primary" title="Hitung ulang"><i class="fas fa-calculator"></i></button>
                <button name="action" value="draft" class="btn btn-primary flex-grow-1">Simpan Draf</button>
                <button name="action" value="final" class="btn btn-success" onclick="return confirm('Kunci laporan SHU {{ $year }} sebagai final?')"><i class="fas fa-lock"></i></button>
            </div>
        </div>
        <small class="text-muted mt-2">Periode {{ tanggal($calc['start_date']) }} s.d. {{ tanggal($calc['end_date']) }}. Laporan final tidak dihitung ulang dan menjadi dasar SHU yang dilihat anggota.</small>
    </form>
@endunless

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Perhitungan SHU {{ $year }}</div>
            <table class="table mb-0">
                <tr><td>Bunga pinjaman diterima</td><td class="text-end">{{ rupiah($src['interest_income']) }}</td></tr>
                <tr><td>Denda keterlambatan</td><td class="text-end">{{ rupiah($src['late_fee_income']) }}</td></tr>
                <tr><td>Pendapatan lain</td><td class="text-end">{{ rupiah($src['other_income']) }}</td></tr>
                <tr class="fw-semibold"><td>Total pendapatan</td><td class="text-end">{{ rupiah($src['total_income']) }}</td></tr>
                <tr><td>Biaya operasional</td><td class="text-end text-danger">({{ rupiah($src['operating_expense']) }})</td></tr>
                <tr class="fs-5 fw-bold"><td>SHU</td><td class="text-end {{ $src['shu_amount'] < 0 ? 'text-danger' : 'text-success' }}">{{ rupiah($src['shu_amount']) }}</td></tr>
            </table>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Alokasi SHU</div>
            <table class="table mb-0">
                @foreach ($posts as $k => $label)
                    <tr><td>{{ $label }}</td><td class="text-muted">{{ (float) $pct[$k] }}%</td><td class="text-end">{{ rupiah($alloc[$k]) }}</td></tr>
                    @if ($k === 'jasa_anggota')
                        <tr class="small"><td class="ps-4">↳ Jasa simpanan</td><td class="text-muted">{{ (float) $pct['jasa_simpanan'] }}%</td><td class="text-end">{{ rupiah($alloc['jasa_simpanan']) }}</td></tr>
                        <tr class="small"><td class="ps-4">↳ Jasa pinjaman</td><td class="text-muted">{{ (float) $pct['jasa_pinjaman'] }}%</td><td class="text-end">{{ rupiah($alloc['jasa_pinjaman']) }}</td></tr>
                    @endif
                @endforeach
            </table>
            @if ($src['shu_amount'] < 0)<div class="card-footer bg-white small text-danger">SHU negatif (rugi) — tidak ada pembagian.</div>@endif
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold">Pembagian SHU per Anggota ({{ count($rows) }} anggota)</div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>NIK</th><th>Nama</th><th>Divisi</th><th class="text-end">Simpanan</th><th class="text-end">Bunga Dibayar</th><th class="text-end">Jasa Simpanan</th><th class="text-end">Jasa Pinjaman</th><th class="text-end">Total SHU</th></tr></thead>
            <tbody>
            @forelse ($rows as $r)
                <tr><td>{{ $r['nik'] }}</td><td>{{ $r['full_name'] }}</td><td>{{ $r['division_name'] ?? '-' }}</td>
                    <td class="text-end">{{ rupiah($r['savings_balance'], false) }}</td><td class="text-end">{{ rupiah($r['interest_paid'], false) }}</td>
                    <td class="text-end">{{ rupiah($r['shu_simpanan'], false) }}</td><td class="text-end">{{ rupiah($r['shu_pinjaman'], false) }}</td>
                    <td class="text-end fw-bold">{{ rupiah($r['shu_total'], false) }}</td></tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada anggota yang berhak menerima SHU.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
