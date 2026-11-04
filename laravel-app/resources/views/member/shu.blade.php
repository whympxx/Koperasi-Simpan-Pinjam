@extends('layouts.app')
@section('title', 'SHU Saya')
@section('content')
@php $row = $share['row']; @endphp
<form class="d-flex gap-2 mb-3 no-print" style="max-width: 320px">
    <select name="year" class="form-select" onchange="this.form.submit()">
        @for ($y = now()->year; $y >= now()->year - 5; $y--)<option value="{{ $y }}" @selected($y === $year)>Tahun Buku {{ $y }}</option>@endfor
    </select>
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print"></i></button>
</form>

@if ($share['source'] === 'final')
    <div class="alert alert-success"><i class="fas fa-lock me-1"></i>Laporan SHU tahun {{ $year }} sudah final dan disahkan pengurus.</div>
@else
    <div class="alert alert-warning"><i class="fas fa-circle-info me-1"></i>Angka di bawah adalah <strong>estimasi</strong> berdasarkan data sampai hari ini dan dapat berubah sampai laporan SHU dikunci pengurus.</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card stat-card p-3"><div class="text-muted small">SHU Jasa Simpanan</div><div class="fs-4 fw-bold">{{ rupiah($row['shu_simpanan'] ?? 0) }}</div></div></div>
    <div class="col-md-4"><div class="card stat-card p-3"><div class="text-muted small">SHU Jasa Pinjaman</div><div class="fs-4 fw-bold">{{ rupiah($row['shu_pinjaman'] ?? 0) }}</div></div></div>
    <div class="col-md-4"><div class="card stat-card p-3 bg-primary text-white"><div class="small">Total SHU Saya</div><div class="fs-4 fw-bold">{{ rupiah($row['shu_total'] ?? 0) }}</div></div></div>
</div>

<div class="card">
    <div class="card-header bg-white fw-semibold">Rincian Perhitungan</div>
    <div class="card-body">
        <table class="table mb-0">
            <tr><td>Simpanan wajib Anda (dasar jasa simpanan)</td><td class="text-end">{{ rupiah($row['savings_balance'] ?? 0) }}</td></tr>
            <tr><td>Bunga pinjaman yang Anda bayar tahun {{ $year }} (dasar jasa pinjaman)</td><td class="text-end">{{ rupiah($row['interest_paid'] ?? 0) }}</td></tr>
            <tr><td>Total SHU koperasi</td><td class="text-end">{{ rupiah($share['shu_amount']) }}</td></tr>
            <tr><td>Pool jasa simpanan seluruh anggota</td><td class="text-end">{{ rupiah($share['pool_simpanan']) }}</td></tr>
            <tr><td>Pool jasa pinjaman seluruh anggota</td><td class="text-end">{{ rupiah($share['pool_pinjaman']) }}</td></tr>
        </table>
        <p class="small text-muted mt-3 mb-0">SHU jasa simpanan = (simpanan Anda ÷ total simpanan anggota) × pool jasa simpanan. SHU jasa pinjaman = (bunga yang Anda bayar ÷ total bunga anggota) × pool jasa pinjaman.</p>
    </div>
</div>
@endsection
