@extends('layouts.guest')
@section('title', 'Masuk')
@section('content')
<div class="mb-4">
    <div class="eyebrow mb-2" style="color:var(--k-gold-500)">Selamat datang</div>
    <h2 class="mb-1">Masuk ke akun Anda</h2>
    <p class="text-muted mb-0">Gunakan NIK karyawan dan password yang diberikan pengurus.</p>
</div>
<form method="POST" action="{{ route('login') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label" for="nik">NIK Karyawan</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-id-badge"></i></span>
            <input type="text" name="nik" id="nik" value="{{ old('nik') }}" class="form-control text-uppercase" placeholder="Contoh: PUN001" autocomplete="username" required autofocus>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
            <input type="password" name="password" id="password" class="form-control" autocomplete="current-password" required>
            <button type="button" class="btn btn-light border" onclick="const p=document.getElementById('password');p.type=p.type==='password'?'text':'password';this.firstElementChild.classList.toggle('fa-eye-slash')" aria-label="Tampilkan password"><i class="fas fa-eye"></i></button>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
            <label class="form-check-label small" for="remember">Ingat saya</label>
        </div>
        <a href="{{ route('password.request') }}" class="small fw-semibold">Lupa password?</a>
    </div>
    <button class="btn btn-primary w-100 py-2"><i class="fas fa-right-to-bracket me-2"></i>Masuk</button>
</form>
<div class="mt-4 p-3 rounded-3 small text-muted" style="background:#eef2f8">
    <i class="fas fa-circle-info me-1"></i>Belum menjadi anggota? Pendaftaran dilakukan melalui pengurus koperasi atau HRD.
</div>
@endsection
