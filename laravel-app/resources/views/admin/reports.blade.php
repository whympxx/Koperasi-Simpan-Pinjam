@extends('layouts.app')
@section('title', 'Laporan Keuangan')
@section('content')
<form class="card card-body mb-3 no-print">
    <div class="row g-2 align-items-end">
        <div class="col-sm-4"><label class="form-label small">Dari</label><input type="date" name="start_date" value="{{ $start }}" class="form-control"></div>
        <div class="col-sm-4"><label class="form-label small">Sampai</label><input type="date" name="end_date" value="{{ $end }}" class="form-control"></div>
        <div class="col-sm-4 d-flex gap-2"><button class="btn btn-primary flex-grow-1">Tampilkan</button><button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print"></i></button></div>
    </div>
</form>
<h5 class="mb-3">{{ config('koperasi.name') }} · {{ tanggal($start) }} s.d. {{ tanggal($end) }}</h5>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Arus Kas Periode (transaksi selesai)</div>
            <table class="table mb-0">
                @foreach (\App\Models\Transaction::TYPES as $k => $label)
                    <tr><td>{{ $label }}</td><td class="text-muted">{{ $summary[$k]->count ?? 0 }} trx</td><td class="text-end">{{ rupiah($summary[$k]->total ?? 0) }}</td></tr>
                @endforeach
            </table>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header bg-white fw-semibold">Pendapatan Pinjaman Periode</div>
            <table class="table mb-0">
                <tr><td>Pokok angsuran diterima</td><td class="text-end">{{ rupiah($interest->principal ?? 0) }}</td></tr>
                <tr><td>Bunga pinjaman diterima</td><td class="text-end">{{ rupiah($interest->interest ?? 0) }}</td></tr>
                <tr><td>Denda keterlambatan diterima</td><td class="text-end">{{ rupiah($interest->late_fee ?? 0) }}</td></tr>
                <tr class="fw-bold"><td>Total pendapatan (bunga + denda)</td><td class="text-end">{{ rupiah(($interest->interest ?? 0) + ($interest->late_fee ?? 0)) }}</td></tr>
            </table>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-semibold">Posisi Saat Ini</div>
            <table class="table mb-0">
                <tr><td>Total simpanan wajib</td><td class="text-end">{{ rupiah($portfolio['total_savings']) }}</td></tr>
                <tr><td>Outstanding pinjaman ({{ $portfolio['active_loans'] }} pinjaman)</td><td class="text-end">{{ rupiah($portfolio['total_outstanding']) }}</td></tr>
                <tr><td>Angsuran terlambat</td><td class="text-end text-danger">{{ rupiah($portfolio['overdue_amount']) }}</td></tr>
                <tr><td>Pengajuan menunggu review</td><td class="text-end">{{ $portfolio['pending_apps'] }}</td></tr>
            </table>
        </div>
    </div>
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Pengajuan Pinjaman Periode</div>
            <table class="table mb-0">
                <thead class="table-light"><tr><th>Status</th><th>Jumlah Pengajuan</th><th class="text-end">Nilai</th></tr></thead>
                @forelse ($applications as $status => $row)
                    <tr><td>{!! status_badge($status) !!}</td><td>{{ $row->count }}</td><td class="text-end">{{ rupiah($row->total) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-center text-muted">Tidak ada pengajuan pada periode ini.</td></tr>
                @endforelse
            </table>
        </div>
    </div>
</div>
@endsection
