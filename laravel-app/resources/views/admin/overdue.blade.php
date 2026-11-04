@extends('layouts.app')
@section('title', 'Angsuran Terlambat')
@section('content')
<div class="alert alert-danger"><i class="fas fa-triangle-exclamation me-1"></i>Total angsuran terlambat: <strong>{{ rupiah($totalAmount) }}</strong> ({{ $schedules->total() }} angsuran), belum termasuk denda.</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Nasabah</th><th>Pinjaman</th><th>Angsuran</th><th>Jatuh Tempo</th><th>Terlambat</th><th class="text-end">Jumlah</th><th class="text-end">Denda</th><th>Pengingat</th><th></th></tr></thead>
            <tbody>
            @forelse ($schedules as $s)
                <tr>
                    <td>{{ $s->loan->user->full_name }}<div class="small text-muted">{{ $s->loan->user->phone }}</div></td>
                    <td><a href="{{ route('admin.active-loans.show', $s->loan) }}">{{ $s->loan->loan_number }}</a></td>
                    <td>ke-{{ $s->installment_number }}</td>
                    <td>{{ tanggal($s->due_date) }}</td>
                    <td class="text-danger">{{ $s->daysLate() }} hari</td>
                    <td class="text-end">{{ rupiah($s->total_amount) }}</td>
                    <td class="text-end text-danger">{{ rupiah(\App\Services\LoanService::lateFee($s)) }}</td>
                    <td class="small">{{ $s->reminder_sent_at ? $s->reminder_sent_at->locale('id')->diffForHumans() : '-' }}</td>
                    <td><form method="POST" action="{{ route('admin.overdue.remind', $s) }}">@csrf<button class="btn btn-sm btn-outline-warning text-nowrap"><i class="fas fa-bell me-1"></i>Ingatkan</button></form></td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">Tidak ada angsuran terlambat. 🎉</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($schedules->hasPages())<div class="card-footer bg-white">{{ $schedules->links() }}</div>@endif
</div>
@endsection
