@extends('layouts.guest')
@section('title', 'Lupa Password')
@section('content')
<h5 class="mb-1">Lupa Password</h5>
<p class="text-muted small">Masukkan NIK dan email terdaftar. Link reset akan dikirim ke email Anda.</p>
<form method="POST" action="{{ route('password.email') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label">NIK</label>
        <input type="text" name="nik" value="{{ old('nik') }}" class="form-control text-uppercase" required>
    </div>
    <div class="mb-4">
        <label class="form-label">Email</label>
        <input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
    </div>
    <button class="btn btn-primary w-100"><i class="fas fa-paper-plane me-2"></i>Kirim Link Reset</button>
</form>
<div class="text-center mt-3"><a href="{{ route('login') }}" class="small"><i class="fas fa-arrow-left me-1"></i>Kembali ke login</a></div>
@endsection
