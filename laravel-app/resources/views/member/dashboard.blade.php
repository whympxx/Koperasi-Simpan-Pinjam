@extends('layouts.app')
@section('title', 'Beranda Anggota')
@section('content')
@php $annColor = ['info' => 'primary', 'rat' => 'warning', 'penting' => 'danger']; @endphp

<div class="hero mb-4">
    <div class="hero-grid"></div>
    <div class="row g-4 align-items-center position-relative">
        <div class="col-lg-7">
            <div class="eyebrow mb-2">{{ $user->categoryLabel() }} · {{ $user->hasPayrollDeduction() ? 'Potongan HRD' : 'Angsuran mandiri' }}</div>
            <h3 class="mb-2">Selamat datang, {{ $user->full_name }}</h3>
            <p class="muted mb-4">Plafon pinjaman Anda saat ini <strong class="text-white">{{ rupiah($maxLoan) }}</strong>, dihitung dari saldo simpanan wajib.</p>
            <div class="d-flex flex-wrap gap-2">
                @if ($activeLoan)
                    <a href="{{ route('loans.payment') }}" class="btn btn-gold"><i class="fas fa-money-bill-wave me-1"></i>Bayar Angsuran</a>
                @elseif ($eligibility['eligible'])
                    <a href="{{ route('loans.create') }}" class="btn btn-gold"><i class="fas fa-file-signature me-1"></i>Ajukan Pinjaman</a>
                @endif
                <a href="{{ route('savings.create') }}" class="btn btn-outline-light"><i class="fas fa-plus me-1"></i>Setor Simpanan</a>
            </div>
        </div>
        <div class="col-lg-5 d-flex justify-content-lg-end">
            <div class="w-100" style="max-width:380px">@include('partials.member-card', ['member' => $user])</div>
        </div>
    </div>
</div>

@if ($pendingCount)
    <div class="alert alert-info"><i class="fas fa-hourglass-half me-2"></i>{{ $pendingCount }} transaksi Anda sedang menunggu verifikasi pengurus.</div>
@endif

<div class="row g-3 mb-4">
    @foreach ([
        ['Simpanan Wajib', $account->balance, 'fa-vault', 'success', 'No. Rek '.$account->account_number],
        ['Plafon Pinjaman', $maxLoan, 'fa-scale-balanced', 'primary', 'Kelipatan simpanan wajib'],
        ['Sisa Pokok Pinjaman', $activeLoan?->remaining_balance ?? 0, 'fa-hand-holding-dollar', 'warning', $activeLoan ? $activeLoan->remaining_months.' angsuran lagi' : 'Tidak ada pinjaman aktif'],
        ['Estimasi SHU '.now()->year, $shu['row']['shu_total'] ?? 0, 'fa-coins', 'info', $shu['source'] === 'final' ? 'Laporan final' : 'Estimasi berjalan'],
    ] as [$label, $value, $icon, $color, $note])
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card h-100" data-tilt="6">
                <div class="d-flex gap-3 align-items-center">
                    <div class="icon bg-{{ $color }}-subtle text-{{ $color }}"><i class="fas {{ $icon }}"></i></div>
                    <div class="min-w-0"><div class="text-muted small">{{ $label }}</div>
                        <div class="fs-5 fw-bold" data-count="{{ (float) $value }}" data-prefix="Rp ">{{ rupiah($value) }}</div>
                        <div class="small text-muted text-truncate">{{ $note }}</div></div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center"><span>Status Pinjaman</span>
                <a href="{{ route('loans.index') }}" class="small fw-semibold">Lihat detail</a></div>
            <div class="card-body">
                @if ($activeLoan)
                    @php $progress = $activeLoan->loan_term_months ? round(($activeLoan->loan_term_months - $activeLoan->remaining_months) / $activeLoan->loan_term_months * 100) : 0; @endphp
                    <div class="d-flex justify-content-between flex-wrap gap-3 mb-3">
                        <div><div class="text-muted small">No. Pinjaman</div><div class="fw-semibold">{{ $activeLoan->loan_number }}</div></div>
                        <div><div class="text-muted small">Pokok</div><div class="fw-semibold">{{ rupiah($activeLoan->original_amount) }}</div></div>
                        <div><div class="text-muted small">Angsuran / bulan</div><div class="fw-semibold">{{ rupiah($activeLoan->monthly_installment) }}</div></div>
                    </div>
                    <div class="d-flex justify-content-between small mb-1"><span class="text-muted">Progres pelunasan</span><span class="fw-semibold">{{ $progress }}%</span></div>
                    <div class="progress mb-3" style="height:10px;border-radius:6px"><div class="progress-bar" style="width:{{ $progress }}%;background:linear-gradient(90deg,var(--k-navy-700),var(--k-gold-500))"></div></div>
                    @if ($nextSchedule)
                        <div class="alert {{ $nextSchedule->isOverdue() ? 'alert-danger' : 'alert-light' }} mb-0 d-flex align-items-center gap-3">
                            <i class="fas fa-calendar-day fa-lg"></i>
                            <div>Angsuran ke-{{ $nextSchedule->installment_number }} sebesar <strong>{{ rupiah($nextSchedule->total_amount) }}</strong>
                                jatuh tempo <strong>{{ tanggal($nextSchedule->due_date) }}</strong>@if ($nextSchedule->isOverdue()) · terlambat {{ $nextSchedule->daysLate() }} hari @endif</div>
                        </div>
                    @endif
                @elseif ($eligibility['eligible'])
                    <div class="text-center py-3">
                        <i class="fas fa-circle-check fa-2x text-success mb-2"></i>
                        <p class="mb-3">Anda memenuhi syarat pengajuan pinjaman hingga <strong>{{ rupiah($maxLoan) }}</strong>.</p>
                        <a href="{{ route('loans.create') }}" class="btn btn-primary">Ajukan Sekarang</a>
                    </div>
                @else
                    <div class="alert alert-warning mb-0"><i class="fas fa-circle-info me-1"></i>Belum dapat mengajukan pinjaman: {{ $eligibility['reason'] }}
                        @if ($eligibility['rule_code'])<span class="badge bg-secondary">{{ $eligibility['rule_code'] }}</span>@endif</div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between"><span>Transaksi Terakhir</span><a href="{{ route('transactions.index') }}" class="small fw-semibold">Semua</a></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    @forelse ($recentTransactions as $t)
                        <tr>
                            <td style="width:44px"><span class="avatar {{ $t->isCredit() ? 'bg-success' : '' }}" style="background:{{ $t->isCredit() ? '#198754' : 'var(--k-navy-800)' }}"><i class="fas {{ $t->isCredit() ? 'fa-arrow-down' : 'fa-arrow-up' }}"></i></span></td>
                            <td><div class="fw-semibold">{{ $t->typeLabel() }}</div><small class="text-muted">{{ tanggal($t->transaction_date) }} · {{ $t->reference_number }}</small></td>
                            <td class="text-end"><div class="fw-semibold {{ $t->isCredit() ? 'text-success' : '' }}">{{ rupiah($t->amount) }}</div>{!! status_badge($t->status) !!}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted py-4">Belum ada transaksi.</td></tr>
                    @endforelse
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header"><i class="fas fa-bullhorn me-2 text-warning"></i>Pengumuman Koperasi</div>
            <div class="list-group list-group-flush">
                @forelse ($announcements as $a)
                    <div class="list-group-item py-3">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="fw-semibold">{{ $a->title }}</div>
                            <span class="badge bg-{{ $annColor[$a->category] }}-subtle text-{{ $annColor[$a->category] }}">{{ \App\Models\Announcement::CATEGORIES[$a->category] }}</span>
                        </div>
                        <div class="small text-muted mt-1" style="white-space:pre-line">{{ $a->body }}</div>
                        <div class="small text-muted mt-1">{{ tanggal($a->created_at) }}</div>
                    </div>
                @empty
                    <div class="list-group-item text-muted text-center small py-4">Belum ada pengumuman.</div>
                @endforelse
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between"><span>Notifikasi Baru</span><a href="{{ route('notifications.index') }}" class="small fw-semibold">Semua</a></div>
            <div class="list-group list-group-flush">
                @forelse ($notifications as $n)
                    <div class="list-group-item"><div class="fw-semibold small">{{ $n->title }}</div><div class="small text-muted text-truncate">{{ $n->message }}</div></div>
                @empty
                    <div class="list-group-item text-muted text-center small py-4">Tidak ada notifikasi baru.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
