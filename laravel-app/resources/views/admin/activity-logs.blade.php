@extends('layouts.app')
@section('title', 'Log Aktivitas')
@section('content')
<form class="card card-body mb-3">
    <div class="row g-2">
        <div class="col-md-5"><select name="action" class="form-select"><option value="">Semua aktivitas</option>
            @foreach ($actions as $a)<option value="{{ $a }}" @selected($action === $a)>{{ str_replace('_', ' ', $a) }}</option>@endforeach</select></div>
        <div class="col-md-5"><input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="Cari nama / NIK pelaku"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button></div>
    </div>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead><tr><th>Waktu</th><th>Pelaku</th><th>Aktivitas</th><th>Objek</th><th>Detail</th><th>IP</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="text-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $log->user?->full_name ?? 'Tamu' }}<div class="small text-muted">{{ $log->user?->nik }}</div></td>
                    <td><span class="badge {{ str_contains($log->action, 'failed') || str_contains($log->action, 'reject') ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' }}">{{ str_replace('_', ' ', $log->action) }}</span></td>
                    <td class="small">{{ $log->entity_type ? $log->entity_type.' #'.$log->entity_id : '-' }}</td>
                    <td class="small text-muted" style="max-width:340px">{{ \Illuminate\Support\Str::limit(json_encode($log->new_values ?? $log->old_values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 140) }}</td>
                    <td class="small">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada log.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($logs->hasPages())<div class="card-footer">{{ $logs->links() }}</div>@endif
</div>
@endsection
