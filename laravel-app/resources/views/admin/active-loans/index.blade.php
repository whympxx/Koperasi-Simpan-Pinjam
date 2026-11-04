@extends('layouts.app')
@section('title', 'Pinjaman Aktif')
@section('content')
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-3 align-items-center">
    <div><div class="text-muted small">Total Outstanding (sisa pokok)</div><div class="fs-4 fw-bold">{{ rupiah($outstanding) }}</div></div>
    <form class="ms-auto d-flex gap-2">
        <select name="status" class="form-select" onchange="this.form.submit()">
            @foreach (['active' => 'Aktif', 'completed' => 'Lunas', 'all' => 'Semua'] as $k => $v)<option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>@endforeach
        </select>
        <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Cari...">
    </form>
</div></div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>No. Pinjaman</th><th>Nasabah</th><th class="text-end">Pokok</th><th class="text-end">Sisa</th><th class="text-end">Angsuran</th><th>Sisa Tenor</th><th>Jatuh Tempo</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($loans as $l)
                <tr>
                    <td><code>{{ $l->loan_number }}</code></td>
                    <td>{{ $l->user->full_name }}<div class="small text-muted">{{ $l->user->nik }}</div></td>
                    <td class="text-end">{{ rupiah($l->original_amount) }}</td>
                    <td class="text-end">{{ rupiah($l->remaining_balance) }}</td>
                    <td class="text-end">{{ rupiah($l->monthly_installment) }}</td>
                    <td>{{ $l->remaining_months }}/{{ $l->loan_term_months }}</td>
                    <td>{{ tanggal($l->next_due_date) }}</td>
                    <td>{!! status_badge($l->status) !!} @if ($l->overdue_count)<span class="badge bg-danger">{{ $l->overdue_count }} terlambat</span>@endif</td>
                    <td><a href="{{ route('admin.active-loans.show', $l) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada pinjaman.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($loans->hasPages())<div class="card-footer bg-white">{{ $loans->links() }}</div>@endif
</div>
@endsection
