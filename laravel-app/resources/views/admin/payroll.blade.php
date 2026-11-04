@extends('layouts.app')
@section('title', 'Potong Gaji Massal')
@section('content')
<div class="hero mb-4">
    <div class="hero-grid"></div>
    <div class="row align-items-center g-3 position-relative">
        <div class="col-lg-7">
            <div class="eyebrow mb-2">Periode {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $period)->locale('id')->translatedFormat('F Y') }}</div>
            <h4 class="mb-2">Pembukuan potongan gaji anggota</h4>
            <p class="muted mb-0">Iuran simpanan wajib {{ rupiah($iuran) }}/bulan dan angsuran yang jatuh tempo s.d. akhir periode dibukukan sekaligus untuk anggota dengan potongan HRD. Data yang sudah dibukukan tidak akan tercatat dua kali.</p>
        </div>
        <div class="col-lg-5 text-lg-end">
            <form class="d-inline-flex gap-2">
                <input type="month" name="period" value="{{ $period }}" max="{{ now()->format('Y-m') }}" class="form-control">
                <button class="btn btn-gold text-nowrap">Tampilkan</button>
            </form>
            <div class="mt-3"><div class="muted small">Total potongan periode ini</div><div class="fs-3 fw-bold">{{ rupiah($grandTotal) }}</div></div>
        </div>
    </div>
</div>

<form method="POST" action="{{ route('admin.payroll.process') }}" onsubmit="return confirm('Bukukan potongan gaji untuk anggota yang dipilih?')">
    @csrf
    <input type="hidden" name="period" value="{{ $period }}">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>{{ $rows->count() }} anggota dengan potongan HRD</span>
            <button class="btn btn-primary btn-sm" @disabled($grandTotal <= 0)><i class="fas fa-check-double me-1"></i>Bukukan yang dipilih</button>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th style="width:36px"><input type="checkbox" class="form-check-input" checked onclick="document.querySelectorAll('.row-check:not(:disabled)').forEach(c => c.checked = this.checked)"></th>
                    <th>Anggota</th><th>Divisi</th><th class="text-end">Iuran Wajib</th><th>Angsuran</th><th class="text-end">Jumlah Angsuran</th><th class="text-end">Total Potongan</th>
                </tr></thead>
                <tbody>
                @forelse ($rows as $r)
                    <tr class="{{ $r['total'] <= 0 ? 'text-muted' : '' }}">
                        <td><input type="checkbox" name="users[]" value="{{ $r['user']->id }}" class="form-check-input row-check" @checked($r['total'] > 0) @disabled($r['total'] <= 0)></td>
                        <td><div class="fw-semibold">{{ $r['user']->full_name }}</div><small class="text-muted">{{ $r['user']->nik }}</small></td>
                        <td>{{ $r['user']->division?->name ?? '-' }}</td>
                        <td class="text-end">@if ($r['deposit_done'])<span class="badge bg-success-subtle text-success">Sudah</span>@else{{ rupiah($r['deposit']) }}@endif</td>
                        <td>@if ($r['schedule'])ke-{{ $r['schedule']->installment_number }} · {{ tanggal($r['schedule']->due_date) }}@else<span class="text-muted">-</span>@endif</td>
                        <td class="text-end">{{ $r['installment'] ? rupiah($r['installment']) : '-' }}</td>
                        <td class="text-end fw-bold">{{ rupiah($r['total']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada anggota dengan potongan HRD.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</form>
@endsection
