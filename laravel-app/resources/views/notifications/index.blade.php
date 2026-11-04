@extends('layouts.app')
@section('title', 'Notifikasi')
@section('content')
@php $icons = ['info' => 'fa-circle-info text-info', 'warning' => 'fa-triangle-exclamation text-warning', 'success' => 'fa-circle-check text-success', 'error' => 'fa-circle-xmark text-danger']; @endphp
<div class="card">
    <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
        <div class="btn-group btn-group-sm flex-wrap">
            @foreach (['all' => 'Semua', 'unread' => 'Belum Dibaca ('.$unread.')', 'read' => 'Sudah Dibaca'] + \App\Models\UserNotification::CATEGORIES as $key => $label)
                <a href="{{ route('notifications.index', ['filter' => $key, 'search' => $search]) }}" class="btn {{ $filter === $key ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
            @endforeach
        </div>
        <form class="ms-auto d-flex gap-2">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input type="search" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="Cari...">
        </form>
        @if ($unread)
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-sm btn-outline-success"><i class="fas fa-check-double me-1"></i>Tandai semua dibaca</button></form>
        @endif
    </div>
    <div class="list-group list-group-flush">
        @forelse ($notifications as $n)
            <div class="list-group-item d-flex gap-3 py-3 {{ $n->is_read ? '' : 'bg-light' }}">
                <i class="fas {{ $icons[$n->type] ?? $icons['info'] }} fa-lg mt-1"></i>
                <div class="flex-grow-1">
                    <div class="fw-semibold">{{ $n->title }} @unless ($n->is_read)<span class="badge bg-primary">Baru</span>@endunless</div>
                    <div class="text-muted" style="white-space: pre-line">{{ $n->message }}</div>
                    <small class="text-muted">{{ $n->created_at->locale('id')->diffForHumans() }} · {{ \App\Models\UserNotification::CATEGORIES[$n->category] ?? $n->category }}</small>
                </div>
                <div class="d-flex flex-column gap-1">
                    @if ($n->action_url)
                        <form method="POST" action="{{ route('notifications.read', $n) }}">@csrf<input type="hidden" name="open" value="1"><button class="btn btn-sm btn-outline-primary w-100">Buka</button></form>
                    @endif
                    @unless ($n->is_read)
                        <form method="POST" action="{{ route('notifications.read', $n) }}">@csrf<button class="btn btn-sm btn-outline-success w-100" title="Tandai dibaca"><i class="fas fa-check"></i></button></form>
                    @endunless
                    <form method="POST" action="{{ route('notifications.destroy', $n) }}" onsubmit="return confirm('Hapus notifikasi ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger w-100" title="Hapus"><i class="fas fa-trash"></i></button></form>
                </div>
            </div>
        @empty
            <div class="list-group-item text-center text-muted py-5"><i class="fas fa-bell-slash fa-2x mb-2"></i><br>Tidak ada notifikasi.</div>
        @endforelse
    </div>
    @if ($notifications->hasPages())<div class="card-footer bg-white">{{ $notifications->links() }}</div>@endif
</div>
@endsection
