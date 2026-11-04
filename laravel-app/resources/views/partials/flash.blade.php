@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-circle-check me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-circle-exclamation me-2"></i>{{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-circle-exclamation me-2"></i>
        @if ($errors->count() === 1){{ $errors->first() }}@else
            <ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        @endif
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
