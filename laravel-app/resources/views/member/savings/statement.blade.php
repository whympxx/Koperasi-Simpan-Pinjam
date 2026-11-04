@extends('layouts.app')
@section('title', 'Rekening Koran Simpanan')
@section('content')
<form class="card card-body mb-3 no-print">
    <div class="row g-2 align-items-end">
        <div class="col-sm-4"><label class="form-label small">Dari</label><input type="date" name="from" value="{{ $from }}" class="form-control"></div>
        <div class="col-sm-4"><label class="form-label small">Sampai</label><input type="date" name="to" value="{{ $to }}" class="form-control"></div>
        <div class="col-sm-4 d-flex gap-2"><button class="btn btn-primary flex-grow-1">Tampilkan</button><button type="button" onclick="window.print()" class="btn btn-outline-secondary"><i class="fas fa-print"></i></button></div>
    </div>
</form>
<div class="card">
    <div class="card-body">
        <div class="text-center mb-3">
            <h5 class="mb-0">{{ config('koperasi.name') }}</h5>
            <div>Rekening Koran Simpanan Wajib · {{ tanggal($from) }} s.d. {{ tanggal($to) }}</div>
        </div>
        <div class="row small mb-3">
            <div class="col-6">Nama: <strong>{{ $user->full_name }}</strong><br>NIK: {{ $user->nik }}</div>
            <div class="col-6 text-end">No. Rekening: <strong>{{ $account->account_number }}</strong><br>Saldo saat ini: <strong>{{ rupiah($account->balance) }}</strong></div>
        </div>
        <table class="table table-sm table-bordered">
            <thead class="table-light"><tr><th>Tanggal</th><th>Referensi</th><th>Keterangan</th><th class="text-end">Masuk</th><th class="text-end">Keluar</th><th class="text-end">Saldo</th></tr></thead>
            <tbody>
            @forelse ($transactions as $t)
                <tr>
                    <td>{{ $t->transaction_date->format('d/m/Y') }}</td>
                    <td>{{ $t->reference_number }}</td>
                    <td>{{ $t->description }}</td>
                    <td class="text-end">{{ $t->isCredit() ? rupiah($t->amount, false) : '' }}</td>
                    <td class="text-end">{{ $t->isCredit() ? '' : rupiah($t->amount, false) }}</td>
                    <td class="text-end">{{ $t->balance_after !== null ? rupiah($t->balance_after, false) : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted">Tidak ada mutasi pada periode ini.</td></tr>
            @endforelse
            </tbody>
            <tfoot class="fw-bold"><tr><td colspan="3">Total</td><td class="text-end">{{ rupiah($totalIn, false) }}</td><td class="text-end">{{ rupiah($totalOut, false) }}</td><td></td></tr></tfoot>
        </table>
    </div>
</div>
@endsection
