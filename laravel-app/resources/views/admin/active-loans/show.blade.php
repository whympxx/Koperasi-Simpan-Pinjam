@extends('layouts.app')
@section('title', 'Pinjaman '.$loan->loan_number)
@section('content')
@php $firstUnpaid = $loan->schedules->firstWhere('status', '!=', 'paid'); @endphp
<div class="card mb-4">
    <div class="card-body row g-3">
        <div class="col-md-3"><div class="text-muted small">Nasabah</div><a href="{{ route('admin.users.show', $loan->user) }}" class="fw-semibold">{{ $loan->user->full_name }}</a><div class="small">{{ $loan->user->nik }}</div></div>
        <div class="col-md-2"><div class="text-muted small">Pokok</div><div class="fw-bold">{{ rupiah($loan->original_amount) }}</div></div>
        <div class="col-md-2"><div class="text-muted small">Sisa Pokok</div><div class="fw-bold text-danger">{{ rupiah($loan->remaining_balance) }}</div></div>
        <div class="col-md-2"><div class="text-muted small">Total Dibayar</div><div class="fw-bold text-success">{{ rupiah($loan->total_paid) }}</div><div class="small">denda {{ rupiah($loan->late_fees) }}</div></div>
        <div class="col-md-3"><div class="text-muted small">Pengajuan</div><a href="{{ route('admin.loans.show', $loan->application) }}">{{ $loan->application->application_number }}</a><div>{!! status_badge($loan->status) !!}</div></div>
    </div>
</div>
@if ($payoff && $payoff['charged']->isNotEmpty())
    <div class="card mb-4 border-warning">
        <div class="card-header"><i class="fas fa-bolt text-warning me-2"></i>Pelunasan Dipercepat</div>
        <div class="card-body row g-3 align-items-center">
            <div class="col-md-7">
                <table class="table table-sm mb-0">
                    <tr><td>Pokok tersisa ({{ $payoff['charged']->count() + $payoff['waived']->count() }} angsuran)</td><td class="text-end">{{ rupiah($payoff['principal']) }}</td></tr>
                    <tr><td>Bunga angsuran jatuh tempo / berjalan ({{ $payoff['charged']->count() }})</td><td class="text-end">{{ rupiah($payoff['interest']) }}</td></tr>
                    <tr><td>Denda keterlambatan</td><td class="text-end">{{ rupiah($payoff['late_fee']) }}</td></tr>
                    <tr class="fw-bold"><td>Total pelunasan</td><td class="text-end">{{ rupiah($payoff['total']) }}</td></tr>
                    <tr class="text-success small"><td>Bunga tidak ditagih</td><td class="text-end">{{ rupiah($payoff['interest_saved']) }}</td></tr>
                </table>
            </div>
            <div class="col-md-5">
                <form method="POST" action="{{ route('admin.active-loans.payoff', $loan) }}" onsubmit="return confirm('Bukukan pelunasan dipercepat sebesar {{ rupiah($payoff['total']) }}? Dana harus sudah diterima.')">@csrf
                    <label class="form-label">Dana diterima melalui</label>
                    <select name="payment_method" class="form-select mb-2">
                        @foreach (\App\Models\Transaction::METHODS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                    <button class="btn btn-gold w-100"><i class="fas fa-check-double me-1"></i>Bukukan Pelunasan</button>
                </form>
            </div>
        </div>
    </div>
@endif
<div class="card">
    <div class="card-header bg-white fw-semibold">Jadwal Angsuran</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>#</th><th>Jatuh Tempo</th><th class="text-end">Pokok</th><th class="text-end">Bunga</th><th class="text-end">Total</th><th class="text-end">Denda</th><th>Dibayar</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($loan->schedules as $s)
                <tr class="{{ $s->isOverdue() ? 'table-danger' : '' }}">
                    <td>{{ $s->installment_number }}</td>
                    <td>{{ tanggal($s->due_date) }}</td>
                    <td class="text-end">{{ rupiah($s->principal_amount, false) }}</td>
                    <td class="text-end">{{ rupiah($s->interest_amount, false) }}</td>
                    <td class="text-end">{{ rupiah($s->total_amount, false) }}</td>
                    <td class="text-end">{{ $s->status === 'paid' ? rupiah($s->late_fee, false) : rupiah(\App\Services\LoanService::lateFee($s), false) }}</td>
                    <td>{{ $s->paid_date ? tanggal($s->paid_date) : '-' }}</td>
                    <td>@if (in_array($s->id, $pendingScheduleIds))<span class="badge bg-info">Menunggu verifikasi</span>@else{!! status_badge($s->isOverdue() ? 'overdue' : $s->status) !!}@endif</td>
                    <td>
                        @if ($loan->status === 'active' && $firstUnpaid && $firstUnpaid->id === $s->id && ! in_array($s->id, $pendingScheduleIds))
                            <form method="POST" action="{{ route('admin.schedules.pay', $s) }}" class="d-flex gap-1" onsubmit="return confirm('Bukukan pembayaran angsuran ke-{{ $s->installment_number }}?')">@csrf
                                <select name="payment_method" class="form-select form-select-sm">
                                    @foreach (\App\Models\Transaction::METHODS as $k => $v)<option value="{{ $k }}" @selected($k === 'payroll_deduction')>{{ $v }}</option>@endforeach
                                </select>
                                <button class="btn btn-sm btn-success text-nowrap">Catat Bayar</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
