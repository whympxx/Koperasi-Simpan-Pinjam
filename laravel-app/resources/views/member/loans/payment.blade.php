@extends('layouts.app')
@section('title', 'Bayar Angsuran')
@section('content')
<div class="card" style="max-width: 620px">
    <div class="card-body p-4">
        <p class="text-muted mb-3">Pinjaman <strong>{{ $loan->loan_number }}</strong> · sisa pokok {{ rupiah($loan->remaining_balance) }} · sisa {{ $loan->remaining_months }} angsuran</p>
        @if (! $next)
            <div class="alert alert-success mb-0">Semua angsuran sudah dibayar.</div>
        @elseif (in_array($next->id, $pendingScheduleIds))
            <div class="alert alert-info mb-0"><i class="fas fa-hourglass-half me-1"></i>Pembayaran angsuran ke-{{ $next->installment_number }} sudah dikirim dan sedang menunggu verifikasi pengurus.</div>
        @else
            <table class="table">
                <tr><th class="fw-normal">Angsuran ke</th><td class="text-end">{{ $next->installment_number }} dari {{ $loan->loan_term_months }}</td></tr>
                <tr><th class="fw-normal">Jatuh tempo</th><td class="text-end">{{ tanggal($next->due_date) }} @if ($next->isOverdue())<span class="badge bg-danger">terlambat {{ $next->daysLate() }} hari</span>@endif</td></tr>
                <tr><th class="fw-normal">Pokok + bunga</th><td class="text-end">{{ rupiah($next->total_amount) }}</td></tr>
                <tr><th class="fw-normal">Denda keterlambatan</th><td class="text-end {{ $lateFee ? 'text-danger' : '' }}">{{ rupiah($lateFee) }}</td></tr>
                <tr class="fs-5"><th>Total dibayar</th><td class="text-end fw-bold">{{ rupiah($next->total_amount + $lateFee) }}</td></tr>
            </table>
            <form method="POST" action="{{ route('loans.pay') }}">
                @csrf
                <input type="hidden" name="schedule_id" value="{{ $next->id }}">
                <div class="mb-3">
                    <label class="form-label">Metode Pembayaran</label>
                    <select name="payment_method" class="form-select" required>
                        @foreach ($methods as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="alert alert-light border small"><i class="fas fa-circle-info me-1"></i>Angsuran dibayar berurutan. Setelah Anda mengirim konfirmasi, pengurus akan memverifikasi pembayaran sebelum angsuran dinyatakan lunas. Anggota dengan potongan gaji HRD tidak perlu membayar manual — pengurus mencatatnya.</div>
                <button class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Kirim Konfirmasi Pembayaran</button>
                <a href="{{ route('loans.index') }}" class="btn btn-light">Kembali</a>
            </form>
        @endif
    </div>
</div>
@endsection
