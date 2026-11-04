<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kwitansi {{ $t->reference_number }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/koppuja.css') }}?v=5" rel="stylesheet">
</head>
<body>
<div class="text-center mt-4 no-print">
    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-1"></i>Cetak Kwitansi</button>
    <button onclick="window.close()" class="btn btn-light border">Tutup</button>
</div>
<div class="receipt">
    <div class="receipt-head">
        <div class="d-flex align-items-center gap-3">
            <div class="brand-emblem"><i class="fas fa-building-columns"></i></div>
            <div><div class="fw-bold" style="letter-spacing:.08em">{{ config('koperasi.short_name') }}</div><div class="small" style="opacity:.7">{{ config('koperasi.name') }}</div></div>
        </div>
        <div class="text-end"><div class="eyebrow">Bukti Transaksi</div><div class="fw-semibold">{{ $t->reference_number }}</div></div>
    </div>
    <div class="p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
            <div>
                <div class="text-muted small">Jenis transaksi</div>
                <div class="fs-5 fw-bold">{{ $t->typeLabel() }}</div>
                <div class="text-muted small mt-2">Jumlah</div>
                <div class="receipt-amount">{{ rupiah($t->amount) }}</div>
            </div>
            <span class="stamp">LUNAS</span>
        </div>
        <table class="table">
            <tr><td class="text-muted" style="width:40%">Nama anggota</td><td class="fw-semibold">{{ $t->user->full_name }}</td></tr>
            <tr><td class="text-muted">NIK</td><td>{{ $t->user->nik }} · {{ $t->user->division?->name ?? '-' }}</td></tr>
            <tr><td class="text-muted">Tanggal transaksi</td><td>{{ tanggal($t->transaction_date) }}</td></tr>
            <tr><td class="text-muted">Keterangan</td><td>{{ $t->description }}</td></tr>
            @if ($t->loan)<tr><td class="text-muted">No. pinjaman</td><td>{{ $t->loan->loan_number }}</td></tr>@endif
            @if ($t->schedule)<tr><td class="text-muted">Angsuran ke</td><td>{{ $t->schedule->installment_number }} (jatuh tempo {{ tanggal($t->schedule->due_date) }})</td></tr>@endif
            @if ($t->balance_after !== null)<tr><td class="text-muted">Saldo simpanan setelah transaksi</td><td>{{ rupiah($t->balance_after) }}</td></tr>@endif
            <tr><td class="text-muted">Metode</td><td>{{ \App\Models\Transaction::METHODS[$t->payment_method] ?? $t->payment_method }}</td></tr>
            <tr><td class="text-muted">Diverifikasi oleh</td><td>{{ $t->processor?->full_name ?? 'Sistem' }}{{ $t->verified_at ? ', '.tanggal($t->verified_at, true) : '' }}</td></tr>
        </table>
        <div class="d-flex justify-content-between align-items-end mt-5">
            <div class="small text-muted">Dicetak {{ tanggal(now(), true) }}<br>Dokumen ini sah tanpa tanda tangan basah.</div>
            <div class="text-center"><div class="small text-muted mb-5">Pengurus,</div><div class="fw-semibold border-top pt-1 px-4">{{ $t->processor?->full_name ?? config('koperasi.short_name') }}</div></div>
        </div>
    </div>
</div>
</body>
</html>
