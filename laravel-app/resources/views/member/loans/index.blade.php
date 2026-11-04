@extends('layouts.app')
@section('title', 'Pinjaman')
@section('content')
@if ($activeLoan)
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Pinjaman Aktif {{ $activeLoan->loan_number }}</span>
            <div class="d-flex gap-2 no-print">
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="fas fa-print me-1"></i>Cetak Jadwal</button>
                <a href="{{ route('loans.payment') }}" class="btn btn-sm btn-primary"><i class="fas fa-money-bill-wave me-1"></i>Bayar Angsuran</a>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3 mb-3 text-center text-md-start">
                <div class="col-6 col-md-3"><div class="text-muted small">Pokok Pinjaman</div><div class="fw-bold">{{ rupiah($activeLoan->original_amount) }}</div></div>
                <div class="col-6 col-md-3"><div class="text-muted small">Sisa Pokok</div><div class="fw-bold text-danger">{{ rupiah($activeLoan->remaining_balance) }}</div></div>
                <div class="col-6 col-md-3"><div class="text-muted small">Angsuran / Bulan</div><div class="fw-bold">{{ rupiah($activeLoan->monthly_installment) }}</div></div>
                <div class="col-6 col-md-3"><div class="text-muted small">Sudah Dibayar</div><div class="fw-bold text-success">{{ rupiah($activeLoan->total_paid) }}</div></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light"><tr><th>#</th><th>Jatuh Tempo</th><th class="text-end">Pokok</th><th class="text-end">Bunga</th><th class="text-end">Total</th><th class="text-end">Denda</th><th>Dibayar</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach ($activeLoan->schedules as $s)
                        <tr class="{{ $s->isOverdue() ? 'table-danger' : '' }}">
                            <td>{{ $s->installment_number }}</td>
                            <td>{{ tanggal($s->due_date) }}</td>
                            <td class="text-end">{{ rupiah($s->principal_amount, false) }}</td>
                            <td class="text-end">{{ rupiah($s->interest_amount, false) }}</td>
                            <td class="text-end">{{ rupiah($s->total_amount, false) }}</td>
                            <td class="text-end">{{ $s->status === 'paid' ? rupiah($s->late_fee, false) : ($s->isOverdue() ? rupiah(\App\Services\LoanService::lateFee($s), false) : '-') }}</td>
                            <td>{{ $s->paid_date ? tanggal($s->paid_date) : '-' }}</td>
                            <td>
                                @if (in_array($s->id, $pendingScheduleIds))<span class="badge bg-info">Menunggu verifikasi</span>
                                @elseif ($s->isOverdue()) {!! status_badge('overdue') !!}
                                @else {!! status_badge($s->status === 'pending' ? 'pending' : $s->status) !!} @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @if ($payoff && $payoff['waived']->isNotEmpty())
        <div class="card mb-4 no-print" data-tilt="3">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <div class="icon bg-warning-subtle text-warning rounded-3 d-grid" style="width:48px;height:48px;place-items:center"><i class="fas fa-bolt"></i></div>
                <div class="flex-grow-1">
                    <div class="fw-bold">Simulasi Pelunasan Dipercepat</div>
                    <div class="small text-muted">Bila dilunasi hari ini: pokok tersisa {{ rupiah($payoff['principal']) }} + bunga berjalan {{ rupiah($payoff['interest']) }}{{ $payoff['late_fee'] ? ' + denda '.rupiah($payoff['late_fee']) : '' }}.
                        Anda menghemat bunga <strong class="text-success">{{ rupiah($payoff['interest_saved']) }}</strong>.</div>
                </div>
                <div class="text-end"><div class="small text-muted">Total pelunasan</div><div class="fs-5 fw-bold">{{ rupiah($payoff['total']) }}</div>
                    <div class="small text-muted">Hubungi pengurus untuk memproses</div></div>
            </div>
        </div>
    @endif
@elseif ($eligibility['eligible'])
    <div class="alert alert-success d-flex justify-content-between align-items-center">
        <span><i class="fas fa-circle-check me-2"></i>Anda memenuhi syarat untuk mengajukan pinjaman.</span>
        <a href="{{ route('loans.create') }}" class="btn btn-success btn-sm">Ajukan Pinjaman</a>
    </div>
@else
    <div class="alert alert-warning"><i class="fas fa-circle-info me-2"></i>Belum dapat mengajukan pinjaman: {{ $eligibility['reason'] }}</div>
@endif

<div class="card">
    <div class="card-header bg-white fw-semibold">Riwayat Pengajuan</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>No. Pengajuan</th><th>Tanggal</th><th class="text-end">Jumlah</th><th>Tenor</th><th class="text-end">Angsuran</th><th>Tujuan</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($applications as $a)
                <tr>
                    <td><code>{{ $a->application_number }}</code></td>
                    <td>{{ tanggal($a->created_at) }}</td>
                    <td class="text-end">{{ rupiah($a->loan_amount) }}</td>
                    <td>{{ $a->loan_term_months }} bln</td>
                    <td class="text-end">{{ rupiah($a->monthly_installment) }}</td>
                    <td>{{ $a->purpose }}</td>
                    <td>{!! status_badge($a->status) !!} @if ($a->auto_approved)<span class="badge bg-light text-dark">otomatis</span>@endif
                        @if ($a->status === 'rejected' && $a->rejected_reason)<div class="small text-danger">{{ $a->rejected_reason }}</div>@endif
                        @if (in_array($a->status, ['pending', 'reviewing']) && $a->review_notes)<div class="small text-muted">{{ $a->review_notes }}</div>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada pengajuan pinjaman.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
