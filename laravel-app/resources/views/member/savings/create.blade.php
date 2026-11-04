@extends('layouts.app')
@section('title', 'Setor Simpanan Wajib')
@section('content')
<div class="card" style="max-width: 560px">
    <div class="card-body p-4">
        <p class="text-muted">Saldo saat ini: <strong>{{ rupiah($account->balance) }}</strong> (No. Rek {{ $account->account_number }})</p>
        <div class="alert alert-info small"><i class="fas fa-circle-info me-1"></i>Lakukan transfer/pembayaran terlebih dahulu, lalu kirim formulir ini. Saldo bertambah setelah pengurus memverifikasi setoran Anda.</div>
        <form method="POST" action="{{ route('savings.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Jumlah Setoran</label>
                <div class="input-group"><span class="input-group-text">Rp</span>
                    <input type="text" name="amount" value="{{ old('amount') }}" class="form-control" data-money required></div>
                <div class="form-text">Minimal {{ rupiah(config('koperasi.min_deposit_amount')) }}, maksimal {{ rupiah(config('koperasi.max_deposit_amount')) }}.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Metode Pembayaran</label>
                <select name="payment_method" class="form-select" required>
                    @foreach ($methods as $key => $label)<option value="{{ $key }}" @selected(old('payment_method') === $key)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="mb-4">
                <label class="form-label">Keterangan (opsional)</label>
                <input type="text" name="description" value="{{ old('description') }}" class="form-control" maxlength="255" placeholder="Mis. simpanan wajib bulan November">
            </div>
            <button class="btn btn-success"><i class="fas fa-paper-plane me-1"></i>Kirim Setoran</button>
            <a href="{{ route('savings.index') }}" class="btn btn-light">Batal</a>
        </form>
    </div>
</div>
@endsection
