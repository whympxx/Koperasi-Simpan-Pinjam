@extends('layouts.app')
@section('title', 'Profil Saya')
@section('content')
<div class="row g-4">
    <div class="col-lg-4">
        @if ($user->isNasabah())
            <div class="card p-4 mb-4">
                <div class="section-title">Kartu Anggota Digital</div>
                @include('partials.member-card', ['member' => $user])
            </div>
        @endif
        <div class="card text-center p-4">
            <div class="avatar mx-auto mb-3" style="width:72px;height:72px;font-size:1.5rem;border-radius:20px">{{ collect(explode(' ', $user->full_name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}</div>
            <h5 class="mb-0">{{ $user->full_name }}</h5>
            <div class="text-muted">{{ $user->nik }}</div>
            <div class="mt-2">{!! status_badge($user->status) !!} <span class="badge bg-primary">{{ \App\Models\User::ROLES[$user->role] }}</span></div>
            <a href="{{ route('password.edit') }}" class="btn btn-outline-primary mt-3"><i class="fas fa-key me-1"></i>Ubah Password</a>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Data Anggota</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $user->email }}</dd>
                    <dt class="col-sm-4">Telepon</dt><dd class="col-sm-8">{{ $user->phone }}</dd>
                    <dt class="col-sm-4">Divisi</dt><dd class="col-sm-8">{{ $user->division?->name ?? '-' }}</dd>
                    <dt class="col-sm-4">Jabatan</dt><dd class="col-sm-8">{{ $user->position ?? '-' }}</dd>
                    @if ($user->isNasabah())
                        <dt class="col-sm-4">Kategori Anggota</dt><dd class="col-sm-8">{{ $user->categoryLabel() }}</dd>
                        <dt class="col-sm-4">Potongan HRD</dt><dd class="col-sm-8">{{ $user->hasPayrollDeduction() ? 'Ya' : 'Tidak (angsur mandiri)' }}</dd>
                        <dt class="col-sm-4">No. Rekening Simpanan</dt><dd class="col-sm-8">{{ $user->savingsAccount?->account_number ?? '-' }}</dd>
                    @endif
                    <dt class="col-sm-4">Tanggal Masuk Kerja</dt><dd class="col-sm-8">{{ tanggal($user->employment_date) }} ({{ $user->employmentMonths() }} bulan)</dd>
                    <dt class="col-sm-4">Alamat</dt><dd class="col-sm-8">{{ $user->address ?? '-' }}</dd>
                    <dt class="col-sm-4">Login Terakhir</dt><dd class="col-sm-8">{{ tanggal($user->last_login_at, true) }}</dd>
                </dl>
                <p class="text-muted small mt-3 mb-0">Perubahan data diri dilakukan melalui pengurus koperasi.</p>
            </div>
        </div>
    </div>
</div>
@endsection
