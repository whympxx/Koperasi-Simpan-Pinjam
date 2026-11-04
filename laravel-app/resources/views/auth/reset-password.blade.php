@extends('layouts.guest')
@section('title', 'Reset Password')
@section('content')
<h5 class="mb-3">Buat Password Baru</h5>
<form method="POST" action="{{ route('password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" value="{{ old('email', $email) }}" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Password Baru</label>
        <input type="password" name="password" class="form-control" required>
        <div class="form-text">Minimal 8 karakter, mengandung huruf besar, huruf kecil, dan angka.</div>
    </div>
    <div class="mb-4">
        <label class="form-label">Konfirmasi Password</label>
        <input type="password" name="password_confirmation" class="form-control" required>
    </div>
    <button class="btn btn-primary w-100">Simpan Password</button>
</form>
@endsection
