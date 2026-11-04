@extends('layouts.app')
@section('title', 'Pengumuman Koperasi')
@section('content')
@php $color = ['info' => 'primary', 'rat' => 'warning', 'penting' => 'danger']; @endphp
<div class="row g-4">
    <div class="col-lg-5">
        <form class="card" method="POST" action="{{ route('admin.announcements.store') }}">
            @csrf
            <div class="card-header">Buat Pengumuman</div>
            <div class="card-body">
                <div class="mb-3"><label class="form-label">Judul</label><input name="title" value="{{ old('title') }}" class="form-control" maxlength="150" required></div>
                <div class="mb-3"><label class="form-label">Isi</label><textarea name="body" rows="5" class="form-control" maxlength="3000" required>{{ old('body') }}</textarea></div>
                <div class="row g-2 mb-3">
                    <div class="col-6"><label class="form-label">Kategori</label><select name="category" class="form-select">
                        @foreach (\App\Models\Announcement::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(old('category') === $k)>{{ $v }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label">Tampil sampai</label><input type="date" name="expires_at" value="{{ old('expires_at') }}" min="{{ today()->toDateString() }}" class="form-control"></div>
                </div>
                <div class="form-check"><input type="checkbox" class="form-check-input" name="is_published" value="1" id="pub" checked><label class="form-check-label" for="pub">Terbitkan sekarang</label></div>
                <div class="form-check"><input type="checkbox" class="form-check-input" name="notify" value="1" id="notify"><label class="form-check-label" for="notify">Kirim juga sebagai notifikasi ke semua anggota</label></div>
            </div>
            <div class="card-footer"><button class="btn btn-primary"><i class="fas fa-bullhorn me-1"></i>Terbitkan</button></div>
        </form>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Daftar Pengumuman</div>
            <div class="list-group list-group-flush">
                @forelse ($announcements as $a)
                    <div class="list-group-item py-3">
                        <div class="d-flex justify-content-between gap-2">
                            <div>
                                <span class="badge bg-{{ $color[$a->category] }}-subtle text-{{ $color[$a->category] }} me-1">{{ \App\Models\Announcement::CATEGORIES[$a->category] }}</span>
                                @unless ($a->is_published)<span class="badge bg-secondary">Draf</span>@endunless
                                @if ($a->expires_at && $a->expires_at->lt(today()))<span class="badge bg-light text-dark">Berakhir</span>@endif
                                <div class="fw-bold mt-1">{{ $a->title }}</div>
                            </div>
                            <div class="d-flex gap-1 align-items-start">
                                <form method="POST" action="{{ route('admin.announcements.update', $a) }}">@csrf @method('PUT')
                                    <input type="hidden" name="title" value="{{ $a->title }}"><input type="hidden" name="body" value="{{ $a->body }}">
                                    <input type="hidden" name="category" value="{{ $a->category }}">
                                    @if ($a->expires_at && $a->expires_at->gte(today()))<input type="hidden" name="expires_at" value="{{ $a->expires_at->toDateString() }}">@endif
                                    @unless ($a->is_published)<input type="hidden" name="is_published" value="1">@endunless
                                    <button class="btn btn-sm btn-outline-secondary" title="{{ $a->is_published ? 'Jadikan draf' : 'Terbitkan' }}"><i class="fas {{ $a->is_published ? 'fa-eye-slash' : 'fa-eye' }}"></i></button>
                                </form>
                                <form method="POST" action="{{ route('admin.announcements.destroy', $a) }}" onsubmit="return confirm('Hapus pengumuman ini?')">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form>
                            </div>
                        </div>
                        <div class="small text-muted mt-1" style="white-space:pre-line">{{ $a->body }}</div>
                        <div class="small text-muted mt-1">{{ tanggal($a->created_at, true) }} · {{ $a->author?->full_name }}{{ $a->expires_at ? ' · tampil s.d. '.tanggal($a->expires_at) : '' }}</div>
                    </div>
                @empty
                    <div class="list-group-item text-center text-muted py-5">Belum ada pengumuman.</div>
                @endforelse
            </div>
            @if ($announcements->hasPages())<div class="card-footer">{{ $announcements->links() }}</div>@endif
        </div>
    </div>
</div>
@endsection
