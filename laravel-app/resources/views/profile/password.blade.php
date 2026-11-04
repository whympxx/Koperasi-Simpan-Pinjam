@extends('layouts.app')
@section('title', 'Ubah Password')
@section('content')
<div class="card" style="max-width: 520px">
    <div class="card-body p-4">
        <form method="POST" action="{{ route('password.change') }}">
            @csrf @method('PUT')
            <div class="mb-3">
                <label class="form-label">Password Lama</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password Baru</label>
                <input type="password" name="password" class="form-control" required>
                <div class="form-text">Minimal 8 karakter, mengandung huruf besar, huruf kecil, dan angka.</div>
            </div>
            <div class="mb-4">
                <label class="form-label">Konfirmasi Password Baru</label>
                <input type="password" name="password_confirmation" class="form-control" required>
            </div>
            <button class="btn btn-primary"><i class="fas fa-save me-1"></i>Simpan</button>
        </form>
    </div>
</div>
@endsection
