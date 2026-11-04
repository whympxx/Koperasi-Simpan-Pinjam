@extends('layouts.app')
@section('title', 'Dashboard Pengurus')
@section('content')
@php
    $cards = [
        ['Pengajuan Menunggu', $stats['pending_applications'], 'fa-file-signature', 'warning', route('admin.loans.index')],
        ['Perlu Verifikasi', $stats['pending_verifications'], 'fa-circle-check', 'info', route('admin.verifications.index')],
        ['Pinjaman Aktif', $stats['active_loans'], 'fa-hand-holding-dollar', 'primary', route('admin.active-loans.index')],
        ['Angsuran Terlambat', $stats['overdue_payments'], 'fa-triangle-exclamation', 'danger', route('admin.overdue.index')],
        ['Total Outstanding', rupiah($stats['total_outstanding']), 'fa-sack-dollar', 'secondary', route('admin.active-loans.index')],
        ['Total Simpanan', rupiah($stats['total_savings']), 'fa-piggy-bank', 'success', route('admin.savings.index')],
        ['Anggota Aktif', $stats['members'], 'fa-users', 'dark', route('admin.users.index')],
    ];
@endphp
<div class="hero mb-4">
    <div class="hero-grid"></div>
    <div class="row g-3 align-items-center position-relative">
        <div class="col-lg-7">
            <div class="eyebrow mb-2">Panel Pengurus</div>
            <h3 class="mb-2">Selamat bekerja, {{ auth()->user()->full_name }}</h3>
            <p class="muted mb-0">{{ $stats['pending_applications'] }} pengajuan dan {{ $stats['pending_verifications'] }} transaksi menunggu tindakan Anda. Aset simpanan anggota saat ini {{ rupiah($stats['total_savings']) }}.</p>
        </div>
        <div class="col-lg-5 d-flex flex-wrap gap-2 justify-content-lg-end">
            <a href="{{ route('admin.verifications.index') }}" class="btn btn-gold"><i class="fas fa-circle-check me-1"></i>Verifikasi</a>
            <a href="{{ route('admin.payroll.index') }}" class="btn btn-outline-light"><i class="fas fa-money-check-dollar me-1"></i>Potong Gaji</a>
            <a href="{{ route('admin.users.create') }}" class="btn btn-outline-light"><i class="fas fa-user-plus me-1"></i>Anggota Baru</a>
        </div>
    </div>
</div>
<div class="row g-3 mb-4">
    @foreach ($cards as [$label, $value, $icon, $color, $url])
        <div class="col-sm-6 col-xl-3">
            <a href="{{ $url }}" class="text-decoration-none text-reset">
                <div class="card stat-card p-3 h-100" data-tilt="6"><div class="d-flex gap-3 align-items-center">
                    <div class="icon bg-{{ $color }}-subtle text-{{ $color }}"><i class="fas {{ $icon }}"></i></div>
                    <div><div class="text-muted small">{{ $label }}</div><div class="fs-5 fw-bold">{{ $value }}</div></div>
                </div></div>
            </a>
        </div>
    @endforeach
</div>

<div class="card mb-4">
    <div class="card-header bg-white fw-semibold">Transaksi 12 Bulan Terakhir</div>
    <div class="card-body"><canvas id="chart" height="90"></canvas></div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between"><span class="fw-semibold">Pengajuan Perlu Review</span><a class="small" href="{{ route('admin.loans.index') }}">Semua</a></div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                @forelse ($pendingApplications as $a)
                    <tr><td><a href="{{ route('admin.loans.show', $a) }}">{{ $a->user->full_name }}</a><div class="small text-muted">{{ $a->application_number }} · {{ $a->loan_term_months }} bln</div></td>
                        <td class="text-end">{{ rupiah($a->loan_amount) }}<div class="small text-muted">{{ $a->created_at->locale('id')->diffForHumans() }}</div></td></tr>
                @empty
                    <tr><td class="text-center text-muted py-4">Tidak ada pengajuan menunggu.</td></tr>
                @endforelse
            </table></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between"><span class="fw-semibold">Angsuran Terlambat</span><a class="small" href="{{ route('admin.overdue.index') }}">Semua</a></div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                @forelse ($overdue as $s)
                    <tr><td>{{ $s->loan->user->full_name }}<div class="small text-muted">{{ $s->loan->loan_number }} · ke-{{ $s->installment_number }}</div></td>
                        <td class="text-end">{{ rupiah($s->total_amount) }}<div class="small text-danger">{{ $s->daysLate() }} hari</div></td></tr>
                @empty
                    <tr><td class="text-center text-muted py-4">Tidak ada tunggakan.</td></tr>
                @endforelse
            </table></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Peminjam Terbesar</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                @forelse ($topBorrowers as $l)
                    <tr><td><a href="{{ route('admin.active-loans.show', $l) }}">{{ $l->user->full_name }}</a><div class="small text-muted">{{ $l->loan_number }}</div></td>
                        <td class="text-end">{{ rupiah($l->remaining_balance) }}<div class="small text-muted">jatuh tempo {{ tanggal($l->next_due_date) }}</div></td></tr>
                @empty
                    <tr><td class="text-center text-muted py-4">Belum ada pinjaman aktif.</td></tr>
                @endforelse
            </table></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between"><span class="fw-semibold">Transaksi Terbaru</span><a class="small" href="{{ route('admin.transactions.index') }}">Semua</a></div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                @forelse ($recentTransactions as $t)
                    <tr><td>{{ $t->user->full_name }}<div class="small text-muted">{{ $t->typeLabel() }} · {{ $t->reference_number }}</div></td>
                        <td class="text-end">{{ rupiah($t->amount) }}<div>{!! status_badge($t->status) !!}</div></td></tr>
                @empty
                    <tr><td class="text-center text-muted py-4">Belum ada transaksi.</td></tr>
                @endforelse
            </table></div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
    new Chart(document.getElementById('chart'), {
        type: 'bar',
        data: {
            labels: @json($chartLabels),
            datasets: [
                { label: 'Setoran Simpanan', data: @json($chart['savings_deposit']), backgroundColor: '#1b4276', borderRadius: 6, maxBarThickness: 34 },
                { label: 'Pencairan Pinjaman', data: @json($chart['loan_disbursement']), backgroundColor: '#c9a227', borderRadius: 6, maxBarThickness: 34 },
                { label: 'Pembayaran Angsuran', data: @json($chart['loan_payment']), backgroundColor: '#7c93b8', borderRadius: 6, maxBarThickness: 34 },
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { labels: { usePointStyle: true, font: { family: 'Plus Jakarta Sans' } } } },
            scales: {
                x: { grid: { display: false }, ticks: { font: { family: 'Plus Jakarta Sans' } } },
                y: { grid: { color: '#eef2f7' }, ticks: { font: { family: 'Plus Jakarta Sans' }, callback: v => 'Rp ' + Number(v).toLocaleString('id-ID') } }
            }
        }
    });
</script>
@endpush
