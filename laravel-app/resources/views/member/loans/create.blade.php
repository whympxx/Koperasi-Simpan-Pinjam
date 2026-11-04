@extends('layouts.app')
@section('title', 'Ajukan Pinjaman')
@section('content')
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('loans.store') }}" id="loanForm">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Jumlah Pinjaman</label>
                        <div class="input-group"><span class="input-group-text">Rp</span>
                            <input type="text" name="loan_amount" id="amount" value="{{ old('loan_amount') }}" class="form-control" data-money required></div>
                        <div class="form-text">Minimal {{ rupiah($minLoan) }}, maksimal <strong>{{ rupiah($maxLoan) }}</strong> ({{ rtrim(rtrim(number_format($multiplier, 2, ',', '.'), '0'), ',') }}× simpanan wajib {{ rupiah($savings) }}).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jangka Waktu</label>
                        <select name="term_months" id="term" class="form-select" required>
                            @for ($i = 1; $i <= $maxTerm; $i++)
                                <option value="{{ $i }}" @selected((int) old('term_months', min(12, $maxTerm)) === $i)>{{ $i }} bulan{{ $i > $standardTerm ? ' (kebijakan khusus)' : '' }}</option>
                            @endfor
                        </select>
                        <div class="form-text">Tenor di atas {{ $standardTerm }} bulan memerlukan surat pernyataan bermaterai dan review pengurus.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tujuan Pinjaman</label>
                        <select name="purpose" class="form-select" required>
                            @foreach (['Pendidikan', 'Kesehatan', 'Renovasi Rumah', 'Kebutuhan Rumah Tangga', 'Modal Usaha', 'Kendaraan', 'Lainnya'] as $p)
                                <option @selected(old('purpose') === $p)>{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan (opsional)</label>
                        <textarea name="description" class="form-control" rows="3" maxlength="1000">{{ old('description') }}</textarea>
                    </div>
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="agreement" id="agreement" value="1" required>
                        <label class="form-check-label" for="agreement">Saya menyetujui angsuran dipotong/dibayar setiap bulan sesuai jadwal dan dikenakan denda {{ \App\Services\Settings::float('late_fee_percentage') }}% per hari bila terlambat.</label>
                    </div>
                    <button class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Kirim Pengajuan</button>
                    <a href="{{ route('loans.index') }}" class="btn btn-light">Batal</a>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold"><i class="fas fa-calculator me-1"></i>Simulasi Angsuran (bunga flat {{ $interestRate }}%/bulan)</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-7 fw-normal">Angsuran pokok</dt><dd class="col-5 text-end" id="cPrincipal">-</dd>
                    <dt class="col-7 fw-normal">Bunga per bulan</dt><dd class="col-5 text-end" id="cInterest">-</dd>
                    <dt class="col-7">Angsuran per bulan</dt><dd class="col-5 text-end fw-bold" id="cInstallment">-</dd>
                    <dt class="col-7 fw-normal">Total pembayaran</dt><dd class="col-5 text-end" id="cTotal">-</dd>
                </dl>
                <div class="alert alert-danger small mt-3 mb-0 d-none" id="cWarn">Jumlah melebihi plafon Anda.</div>
            </div>
        </div>
        <div class="card"><div class="card-body small text-muted">
            <strong>Ketentuan (RAT 2025):</strong>
            <ul class="mb-0 ps-3">
                <li>Plafon {{ $user->hasPayrollDeduction() ? 'karyawan/driver dengan potongan HRD' : 'outsourcing/angsur mandiri' }}: {{ rtrim(rtrim(number_format($multiplier, 2, ',', '.'), '0'), ',') }}× simpanan wajib.</li>
                <li>Tenor standar maksimal {{ $standardTerm }} bulan, kebijakan khusus maksimal {{ $maxTerm }} bulan.</li>
                <li>Pengajuan yang memenuhi kriteria disetujui dan dicairkan otomatis; lainnya direview pengurus.</li>
            </ul>
        </div></div>
    </div>
</div>
@endsection
@push('scripts')
<script>
    (function () {
        const rate = {{ $interestRate }}, max = {{ $maxLoan }};
        const amount = document.getElementById('amount'), term = document.getElementById('term');
        const fmt = n => 'Rp ' + Math.round(n).toLocaleString('id-ID');
        function calc() {
            const a = Number(amount.value.replace(/[^0-9]/g, '')) || 0, t = Number(term.value) || 1;
            const p = a / t, i = a * rate / 100;
            document.getElementById('cPrincipal').textContent = fmt(p);
            document.getElementById('cInterest').textContent = fmt(i);
            document.getElementById('cInstallment').textContent = fmt(p + i);
            document.getElementById('cTotal').textContent = fmt((p + i) * t);
            document.getElementById('cWarn').classList.toggle('d-none', a <= max);
        }
        amount.addEventListener('input', calc); term.addEventListener('change', calc); calc();
    })();
</script>
@endpush
