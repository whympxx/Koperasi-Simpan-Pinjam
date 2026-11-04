@extends('layouts.app')
@section('title', 'Semua Transaksi')
@section('content')
<div class="row g-3 mb-3">
    @foreach (['savings_deposit' => 'success', 'loan_disbursement' => 'primary', 'loan_payment' => 'warning', 'savings_withdrawal' => 'secondary'] as $k => $c)
        <div class="col-6 col-lg-3"><div class="card stat-card p-3"><div class="text-muted small">{{ \App\Models\Transaction::TYPES[$k] }}</div><div class="fs-5 fw-bold text-{{ $c }}">{{ rupiah($totals[$k] ?? 0) }}</div></div></div>
    @endforeach
</div>
<form class="card card-body mb-3">
    <div class="row g-2">
        <div class="col-md-4"><select name="type" class="form-select"><option value="all">Semua jenis</option>
            @foreach (\App\Models\Transaction::TYPES as $k => $v)<option value="{{ $k }}" @selected($type === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-3"><select name="status" class="form-select"><option value="all">Semua status</option>
            @foreach (['pending' => 'Menunggu', 'completed' => 'Selesai', 'failed' => 'Ditolak'] as $k => $v)<option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-4"><input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Cari referensi / nama / NIK"></div>
        <div class="col-md-1"><button class="btn btn-primary w-100"><i class="fas fa-filter"></i></button></div>
    </div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Tanggal</th><th>Referensi</th><th>Nasabah</th><th>Jenis</th><th>Keterangan</th><th class="text-end">Jumlah</th><th>Status</th><th>Diproses</th><th></th></tr></thead>
            <tbody>
            @forelse ($transactions as $t)
                <tr>
                    <td>{{ tanggal($t->transaction_date) }}</td>
                    <td><code>{{ $t->reference_number }}</code></td>
                    <td>{{ $t->user->full_name }}</td>
                    <td>{{ $t->typeLabel() }}</td>
                    <td class="small">{{ $t->description }}</td>
                    <td class="text-end">{{ rupiah($t->amount) }}</td>
                    <td>{!! status_badge($t->status) !!}</td>
                    <td class="small">{{ $t->processor?->full_name ?? '-' }}</td>
                    <td>@if ($t->status === 'completed')<a href="{{ route('receipts.show', $t) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Kwitansi"><i class="fas fa-receipt"></i></a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada transaksi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($transactions->hasPages())<div class="card-footer bg-white">{{ $transactions->links() }}</div>@endif
</div>
@endsection
