@extends('layouts.app')
@section('title', 'Detail Anggota')
@section('content')
@php $me = auth()->user(); $canEdit = $me->id !== $user->id && (! $user->isSuperAdmin() || $me->isSuperAdmin()); @endphp
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div><h4 class="mb-0">{{ $user->full_name }}</h4><span class="text-muted">{{ $user->nik }} · {{ \App\Models\User::ROLES[$user->role] }}</span> {!! status_badge($user->status) !!}</div>
    @if ($canEdit && $user->status !== 'resigned')
        <form method="POST" action="{{ route('admin.users.toggle', $user) }}" onsubmit="return confirm('Ubah status akun ini?')">@csrf
            <button class="btn {{ $user->status === 'active' ? 'btn-outline-warning' : 'btn-outline-success' }}">
                <i class="fas fa-power-off me-1"></i>{{ $user->status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}</button></form>
    @endif
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header bg-white fw-semibold">Data Anggota</div>
            <div class="card-body">
                @if ($canEdit)
                    <form method="POST" action="{{ route('admin.users.update', $user) }}">@csrf @method('PUT')
                        @include('admin.users._form', ['user' => $user])
                        <button class="btn btn-primary mt-3"><i class="fas fa-save me-1"></i>Simpan Perubahan</button>
                    </form>
                @else
                    <p class="text-muted mb-0">Anda tidak dapat mengubah akun ini dari menu ini.</p>
                @endif
            </div>
        </div>
        @if ($user->isNasabah())
            <div class="card">
                <div class="card-header bg-white fw-semibold">Transaksi Terakhir</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    @forelse ($transactions as $t)
                        <tr><td>{{ tanggal($t->transaction_date) }}</td><td>{{ $t->typeLabel() }}</td><td class="text-end">{{ rupiah($t->amount) }}</td><td>{!! status_badge($t->status) !!}</td></tr>
                    @empty
                        <tr><td class="text-center text-muted py-3">Belum ada transaksi.</td></tr>
                    @endforelse
                </table></div>
            </div>
        @endif
    </div>

    @if ($user->isNasabah())
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between"><span class="text-muted">Simpanan Wajib</span><strong>{{ rupiah($user->savingsAccount?->balance ?? 0) }}</strong></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">No. Rekening</span><span>{{ $user->savingsAccount?->account_number ?? '-' }}</span></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">Plafon Pinjaman</span><strong>{{ rupiah($maxLoan) }}</strong></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">Masa Kerja</span><span>{{ $user->employmentMonths() }} bulan</span></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">Pinjaman Aktif</span>
                        <span>@if ($activeLoan)<a href="{{ route('admin.active-loans.show', $activeLoan) }}">{{ rupiah($activeLoan->remaining_balance) }}</a>@else - @endif</span></div>
                </div>
            </div>

            @if ($user->status === 'active')
                <div class="card mb-4">
                    <div class="card-header bg-white fw-semibold">Catat Setoran Simpanan</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.users.deposit', $user) }}">@csrf
                            <div class="input-group mb-2"><span class="input-group-text">Rp</span><input type="text" name="amount" class="form-control" data-money required></div>
                            <select name="payment_method" class="form-select mb-2">
                                @foreach (\App\Models\Transaction::METHODS as $k => $v)<option value="{{ $k }}" @selected($k === 'payroll_deduction')>{{ $v }}</option>@endforeach
                            </select>
                            <input type="text" name="description" class="form-control mb-2" placeholder="Keterangan (opsional)">
                            <button class="btn btn-success w-100"><i class="fas fa-plus me-1"></i>Bukukan Setoran</button>
                        </form>
                    </div>
                </div>
            @endif

            @if ($canEdit && $user->status !== 'resigned')
                <div class="card border-danger">
                    <div class="card-header bg-white text-danger fw-semibold">Keluar Keanggotaan</div>
                    <div class="card-body">
                        <p class="small text-muted">Seluruh simpanan wajib ({{ rupiah($user->savingsAccount?->balance ?? 0) }}) dikembalikan, rekening ditutup, dan status menjadi "Keluar". Hanya bisa bila tidak ada pinjaman aktif.</p>
                        <form method="POST" action="{{ route('admin.users.close', $user) }}" onsubmit="return confirm('Proses keluar keanggotaan? Tindakan ini tidak dapat dibatalkan.')">@csrf
                            <select name="payment_method" class="form-select mb-2"><option value="transfer">Transfer Bank</option><option value="cash">Tunai</option></select>
                            <input type="text" name="notes" class="form-control mb-2" placeholder="Catatan (mis. resign per tanggal ...)">
                            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirmClose" required>
                                <label class="form-check-label small" for="confirmClose">Saya sudah memastikan data ini benar.</label></div>
                            <button class="btn btn-outline-danger w-100">Proses Keluar Anggota</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
