@extends('layouts.app')
@section('title', 'Verifikasi Setoran & Pembayaran')
@section('content')
<p class="text-muted">Setoran simpanan dan pembayaran angsuran yang dikirim nasabah baru dibukukan setelah dicocokkan dengan bukti transfer / kas.</p>
<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead class="table-light"><tr><th>Tanggal</th><th>Referensi</th><th>Nasabah</th><th>Jenis</th><th>Metode</th><th class="text-end">Jumlah</th><th style="min-width: 320px">Tindakan</th></tr></thead>
            <tbody>
            @forelse ($transactions as $t)
                <tr>
                    <td>{{ tanggal($t->transaction_date) }}<div class="small text-muted">{{ $t->created_at->format('H:i') }}</div></td>
                    <td><code>{{ $t->reference_number }}</code></td>
                    <td>{{ $t->user->full_name }}<div class="small text-muted">{{ $t->user->nik }}</div></td>
                    <td>{{ $t->typeLabel() }}<div class="small text-muted">{{ $t->description }}</div></td>
                    <td>{{ \App\Models\Transaction::METHODS[$t->payment_method] ?? $t->payment_method }}</td>
                    <td class="text-end fw-bold">{{ rupiah($t->amount) }}</td>
                    <td>
                        <div class="d-flex gap-2">
                            <form method="POST" action="{{ route('admin.verifications.approve', $t) }}" onsubmit="return confirm('Terima dan bukukan transaksi ini?')">@csrf
                                <button class="btn btn-sm btn-success"><i class="fas fa-check me-1"></i>Terima</button></form>
                            <form method="POST" action="{{ route('admin.verifications.reject', $t) }}" class="d-flex gap-1 flex-grow-1">@csrf
                                <input type="text" name="reason" class="form-control form-control-sm" placeholder="Alasan tolak" required>
                                <button class="btn btn-sm btn-outline-danger">Tolak</button></form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-5"><i class="fas fa-circle-check fa-2x text-success mb-2"></i><br>Tidak ada transaksi yang menunggu verifikasi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($transactions->hasPages())<div class="card-footer bg-white">{{ $transactions->links() }}</div>@endif
</div>
@endsection
