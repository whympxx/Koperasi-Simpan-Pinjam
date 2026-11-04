@extends('layouts.app')
@section('title', 'Simpanan Wajib')
@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card stat-card p-3"><div class="text-muted small">Saldo Simpanan Wajib</div><div class="fs-4 fw-bold text-success">{{ rupiah($account->balance) }}</div><small class="text-muted">No. Rek {{ $account->account_number }}</small></div></div>
    <div class="col-md-4"><div class="card stat-card p-3"><div class="text-muted small">Total Setoran Dibukukan</div><div class="fs-4 fw-bold">{{ rupiah($totalDeposits) }}</div></div></div>
    <div class="col-md-4"><div class="card stat-card p-3"><div class="text-muted small">Menunggu Verifikasi</div><div class="fs-4 fw-bold text-warning">{{ rupiah($pendingDeposits) }}</div></div></div>
</div>

<div class="alert alert-light border"><i class="fas fa-lock me-2"></i>Sesuai RAT 2025, simpanan wajib tidak dapat diambil selama masih menjadi anggota. Pengembalian simpanan dilakukan pengurus saat Anda keluar keanggotaan. Simpanan sukarela telah ditiadakan.</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Mutasi Simpanan</span>
        <div>
            <a href="{{ route('savings.statement') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-lines me-1"></i>Rekening Koran</a>
            <a href="{{ route('savings.create') }}" class="btn btn-sm btn-success"><i class="fas fa-plus me-1"></i>Setor</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Tanggal</th><th>No. Referensi</th><th>Jenis</th><th>Keterangan</th><th class="text-end">Jumlah</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($transactions as $t)
                <tr>
                    <td>{{ tanggal($t->transaction_date) }}</td>
                    <td><code>{{ $t->reference_number }}</code></td>
                    <td>{{ $t->typeLabel() }}</td>
                    <td>{{ $t->description }} @if ($t->notes)<div class="small text-danger">{{ $t->notes }}</div>@endif</td>
                    <td class="text-end {{ $t->isCredit() ? 'text-success' : 'text-danger' }}">{{ $t->isCredit() ? '+' : '-' }}{{ rupiah($t->amount) }}</td>
                    <td>{!! status_badge($t->status) !!}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada mutasi simpanan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($transactions->hasPages())<div class="card-footer bg-white">{{ $transactions->links() }}</div>@endif
</div>
@endsection
