@extends('layouts.app')
@section('title', 'Pengajuan Pinjaman')
@section('content')
@php $tabs = ['pending' => 'Perlu Review', 'disbursed' => 'Dicairkan', 'rejected' => 'Ditolak', 'all' => 'Semua']; @endphp
<div class="card">
    <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
        <ul class="nav nav-pills">
            @foreach ($tabs as $k => $v)
                @php $n = $k === 'pending' ? ($counts['pending'] ?? 0) + ($counts['reviewing'] ?? 0) : ($k === 'all' ? $counts->sum() : ($counts[$k] ?? 0)); @endphp
                <li class="nav-item"><a class="nav-link py-1 {{ $status === $k ? 'active' : '' }}" href="{{ route('admin.loans.index', ['status' => $k, 'search' => $search]) }}">{{ $v }} <span class="badge bg-light text-dark">{{ $n }}</span></a></li>
            @endforeach
        </ul>
        <form class="ms-auto"><input type="hidden" name="status" value="{{ $status }}"><input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Cari nama / NIK / nomor"></form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>No. Pengajuan</th><th>Nasabah</th><th class="text-end">Jumlah</th><th>Tenor</th><th>Catatan Sistem</th><th>Tanggal</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($applications as $a)
                <tr>
                    <td><code>{{ $a->application_number }}</code></td>
                    <td>{{ $a->user->full_name }}<div class="small text-muted">{{ $a->user->nik }} · {{ $a->user->categoryLabel() }}</div></td>
                    <td class="text-end">{{ rupiah($a->loan_amount) }}</td>
                    <td>{{ $a->loan_term_months }} bln</td>
                    <td class="small" style="max-width: 260px">{{ $a->review_notes }}</td>
                    <td>{{ tanggal($a->created_at) }}</td>
                    <td>{!! status_badge($a->status) !!} @if ($a->auto_approved)<span class="badge bg-light text-dark">otomatis</span>@endif</td>
                    <td><a href="{{ route('admin.loans.show', $a) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada pengajuan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($applications->hasPages())<div class="card-footer bg-white">{{ $applications->links() }}</div>@endif
</div>
@endsection
