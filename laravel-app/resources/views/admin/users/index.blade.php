@extends('layouts.app')
@section('title', 'Manajemen Anggota')
@section('content')
<form class="card card-body mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4"><input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Cari nama, NIK, atau email"></div>
        <div class="col-md-3"><select name="role" class="form-select"><option value="all">Semua peran</option>
            @foreach (\App\Models\User::ROLES as $k => $v)<option value="{{ $k }}" @selected($role === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-3"><select name="status" class="form-select"><option value="all">Semua status</option>
            @foreach (\App\Models\User::STATUSES as $k => $v)<option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="fas fa-filter"></i></button>
            <a href="{{ route('admin.users.create') }}" class="btn btn-success" title="Daftarkan nasabah"><i class="fas fa-user-plus"></i></a></div>
    </div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>NIK</th><th>Nama</th><th>Divisi</th><th>Peran / Kategori</th><th class="text-end">Simpanan</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($users as $u)
                <tr>
                    <td><code>{{ $u->nik }}</code></td>
                    <td>{{ $u->full_name }}<div class="small text-muted">{{ $u->email }}</div></td>
                    <td>{{ $u->division?->name ?? '-' }}</td>
                    <td>{{ \App\Models\User::ROLES[$u->role] }} @if ($u->isNasabah())<div class="small text-muted">{{ $u->categoryLabel() }}{{ $u->hasPayrollDeduction() ? '' : ' · mandiri' }}</div>@endif</td>
                    <td class="text-end">{{ $u->isNasabah() ? rupiah($u->savingsAccount?->balance ?? 0) : '-' }}</td>
                    <td>{!! status_badge($u->status) !!}</td>
                    <td class="text-end"><a href="{{ route('admin.users.show', $u) }}" class="btn btn-sm btn-outline-primary">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($users->hasPages())<div class="card-footer bg-white">{{ $users->links() }}</div>@endif
</div>
@endsection
