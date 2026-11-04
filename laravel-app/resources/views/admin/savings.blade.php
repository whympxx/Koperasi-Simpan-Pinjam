@extends('layouts.app')
@section('title', 'Rekening Simpanan')
@section('content')
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-4 align-items-center">
    <div><div class="text-muted small">Total Simpanan Wajib</div><div class="fs-4 fw-bold text-success">{{ rupiah($totalBalance) }}</div></div>
    <div><div class="text-muted small">Rekening Aktif</div><div class="fs-4 fw-bold">{{ $activeCount }}</div></div>
    <form class="ms-auto d-flex gap-2">
        <select name="status" class="form-select" onchange="this.form.submit()">
            @foreach (['active' => 'Aktif', 'closed' => 'Ditutup', 'all' => 'Semua'] as $k => $v)<option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>@endforeach
        </select>
        <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Cari...">
    </form>
</div></div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>No. Rekening</th><th>Nasabah</th><th>Dibuka</th><th>Transaksi Terakhir</th><th class="text-end">Saldo</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($accounts as $a)
                <tr>
                    <td><code>{{ $a->account_number }}</code></td>
                    <td><a href="{{ route('admin.users.show', $a->user) }}">{{ $a->user->full_name }}</a><div class="small text-muted">{{ $a->user->nik }}</div></td>
                    <td>{{ tanggal($a->opened_date) }}</td>
                    <td>{{ tanggal($a->last_transaction_at) }}</td>
                    <td class="text-end fw-semibold">{{ rupiah($a->balance) }}</td>
                    <td>{!! status_badge($a->status) !!}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada rekening.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($accounts->hasPages())<div class="card-footer bg-white">{{ $accounts->links() }}</div>@endif
</div>
@endsection
