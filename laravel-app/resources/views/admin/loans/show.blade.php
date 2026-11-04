@extends('layouts.app')
@section('title', 'Detail Pengajuan '.$application->application_number)
@section('content')
@php $statusIcon = ['fired' => 'fa-circle-check text-success', 'not_fired' => 'fa-circle-xmark text-secondary', 'skipped' => 'fa-circle-minus text-muted']; @endphp
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header bg-white d-flex justify-content-between"><span class="fw-semibold">Pengajuan</span>{!! status_badge($application->status) !!}</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Jumlah Pinjaman</dt><dd class="col-sm-7 fs-5 fw-bold">{{ rupiah($application->loan_amount) }}</dd>
                    <dt class="col-sm-5">Tenor</dt><dd class="col-sm-7">{{ $application->loan_term_months }} bulan</dd>
                    <dt class="col-sm-5">Bunga</dt><dd class="col-sm-7">{{ $application->interest_rate }}% per bulan (flat)</dd>
                    <dt class="col-sm-5">Angsuran / bulan</dt><dd class="col-sm-7">{{ rupiah($application->monthly_installment) }}</dd>
                    <dt class="col-sm-5">Total Pembayaran</dt><dd class="col-sm-7">{{ rupiah($application->total_payment) }}</dd>
                    <dt class="col-sm-5">Tujuan</dt><dd class="col-sm-7">{{ $application->purpose }}</dd>
                    <dt class="col-sm-5">Keterangan</dt><dd class="col-sm-7">{{ $application->description ?: '-' }}</dd>
                    <dt class="col-sm-5">Diajukan</dt><dd class="col-sm-7">{{ tanggal($application->created_at, true) }}</dd>
                    <dt class="col-sm-5">Catatan Sistem / Review</dt><dd class="col-sm-7">{{ $application->review_notes ?: '-' }}</dd>
                    @if ($application->reviewed_at)
                        <dt class="col-sm-5">Diproses</dt><dd class="col-sm-7">{{ tanggal($application->reviewed_at, true) }} oleh {{ $application->reviewer?->full_name ?? 'Sistem (otomatis)' }}</dd>
                    @endif
                    @if ($application->rejected_reason)<dt class="col-sm-5">Alasan Ditolak</dt><dd class="col-sm-7 text-danger">{{ $application->rejected_reason }}</dd>@endif
                    @if ($application->activeLoan)<dt class="col-sm-5">Pinjaman</dt><dd class="col-sm-7"><a href="{{ route('admin.active-loans.show', $application->activeLoan) }}">{{ $application->activeLoan->loan_number }}</a></dd>@endif
                </dl>
            </div>
        </div>

        @if ($application->isReviewable())
            <div class="card mb-4">
                <div class="card-header bg-white fw-semibold">Keputusan Pengurus</div>
                <div class="card-body row g-3">
                    <form method="POST" action="{{ route('admin.loans.approve', $application) }}" class="col-md-6" onsubmit="return confirm('Setujui dan cairkan pinjaman ini?')">@csrf
                        <textarea name="notes" class="form-control mb-2" rows="2" placeholder="Catatan persetujuan (opsional)"></textarea>
                        <button class="btn btn-success w-100"><i class="fas fa-check me-1"></i>Setujui & Cairkan</button>
                    </form>
                    <form method="POST" action="{{ route('admin.loans.reject', $application) }}" class="col-md-6">@csrf
                        <textarea name="reason" class="form-control mb-2" rows="2" placeholder="Alasan penolakan (wajib)" required></textarea>
                        <button class="btn btn-outline-danger w-100"><i class="fas fa-xmark me-1"></i>Tolak</button>
                    </form>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header bg-white fw-semibold">Jejak Penalaran Rule Engine</div>
            <div class="card-body">
                @forelse ($logs as $log)
                    <div class="mb-3">
                        <div class="fw-semibold">{{ \App\Services\RuleEngine::groups()[$log->rule_group] ?? $log->rule_group }}:
                            <span class="badge bg-dark">{{ $log->decision }}</span> @if ($log->fired_rule_code)<span class="badge bg-secondary">{{ $log->fired_rule_code }}</span>@endif</div>
                        <ul class="list-unstyled small mb-0 mt-1">
                            @foreach ($log->trace ?? [] as $t)
                                <li><i class="fas {{ $statusIcon[$t['status']] ?? '' }} me-1"></i><strong>{{ $t['rule_code'] }}</strong> {{ $t['rule_name'] }} <span class="text-muted">— JIKA {{ $t['if_text'] }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <p class="text-muted mb-0">Tidak ada log evaluasi.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header bg-white fw-semibold">Nasabah</div>
            <div class="card-body">
                <h6 class="mb-0"><a href="{{ route('admin.users.show', $member) }}">{{ $member->full_name }}</a></h6>
                <div class="text-muted small mb-2">{{ $member->nik }} · {{ $member->division?->name ?? '-' }} · {{ $member->position }}</div>
                <div class="small">{{ $member->phone }} · {{ $member->email }}</div>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-semibold">Fakta Saat Ini</div>
            <div class="table-responsive"><table class="table table-sm mb-0 small">
                @foreach ($facts as $key => $value)
                    <tr><td>{{ \App\Services\RuleEngine::factLabel($key) }}</td>
                        <td class="text-end fw-semibold">{{ is_numeric($value) && $value > 1000 ? rupiah($value, false) : $value }}</td></tr>
                @endforeach
            </table></div>
        </div>
    </div>
</div>
@endsection
