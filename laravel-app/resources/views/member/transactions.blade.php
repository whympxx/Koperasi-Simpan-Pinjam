@extends('layouts.app')
@section('title', 'Riwayat Transaksi')
@section('content')
<form class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small">Jenis</label>
            <select name="type" class="form-select"><option value="">Semua</option>
                @foreach (\App\Models\Transaction::TYPES as $k => $v)<option value="{{ $k }}" @selected(($filters['type'] ?? '') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label small">Status</label>
            <select name="status" class="form-select"><option value="">Semua</option>
                @foreach (['pending' => 'Menunggu', 'completed' => 'Selesai', 'failed' => 'Ditolak'] as $k => $v)<option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label small">Dari</label><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label small">Sampai</label><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control"></div>
        <div class="col-md-1"><button class="btn btn-primary w-100"><i class="fas fa-filter"></i></button></div>
    </div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Tanggal</th><th>Referensi</th><th>Jenis</th><th>Keterangan</th><th>Metode</th><th class="text-end">Jumlah</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($transactions as $t)
                <tr>
                    <td>{{ tanggal($t->transaction_date) }}</td>
                    <td><code>{{ $t->reference_number }}</code></td>
                    <td>{{ $t->typeLabel() }}</td>
                    <td>{{ $t->description }} @if ($t->notes)<div class="small text-danger">{{ $t->notes }}</div>@endif</td>
                    <td>{{ \App\Models\Transaction::METHODS[$t->payment_method] ?? $t->payment_method }}</td>
                    <td class="text-end {{ $t->isCredit() ? 'text-success' : 'text-danger' }}">{{ rupiah($t->amount) }}</td>
                    <td>{!! status_badge($t->status) !!}</td>
                    <td class="text-end">@if ($t->status === 'completed')<a href="{{ route('receipts.show', $t) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Kwitansi"><i class="fas fa-receipt"></i></a>@endif</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada transaksi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($transactions->hasPages())<div class="card-footer bg-white">{{ $transactions->links() }}</div>@endif
</div>
@endsection
