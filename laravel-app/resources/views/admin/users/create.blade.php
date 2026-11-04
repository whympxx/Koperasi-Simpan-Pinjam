@extends('layouts.app')
@section('title', 'Daftarkan Nasabah')
@section('content')
<div class="card" style="max-width: 900px">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">NIK Karyawan</label>
                    <input type="text" name="nik" value="{{ old('nik') }}" class="form-control text-uppercase" maxlength="20" required>
                </div>
            </div>
            @include('admin.users._form', ['user' => null])
            <hr>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Password Awal</label>
                    <input type="password" name="password" class="form-control" required>
                    <div class="form-text">Minimal 8 karakter, huruf besar, huruf kecil, dan angka.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Konfirmasi Password</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
            </div>
            <p class="small text-muted mt-3">Rekening simpanan wajib dibuka otomatis setelah pendaftaran.</p>
            <button class="btn btn-primary"><i class="fas fa-user-plus me-1"></i>Daftarkan</button>
            <a href="{{ route('admin.users.index') }}" class="btn btn-light">Batal</a>
        </form>
    </div>
</div>
@endsection
