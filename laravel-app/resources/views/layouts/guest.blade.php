<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('koperasi.short_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/koppuja.css') }}?v=3" rel="stylesheet">
</head>
<body>
<div class="auth-wrap">
    <section class="auth-visual">
        <div id="hero3d" class="position-absolute top-0 start-0 w-100 h-100" aria-hidden="true"></div>
        <div class="d-flex align-items-center gap-2">
            <div class="brand-emblem"><i class="fas fa-building-columns"></i></div>
            <div><div class="fw-bold" style="letter-spacing:.08em">{{ config('koperasi.short_name') }}</div><div class="small" style="color:rgba(255,255,255,.6)">Koperasi Konsumen Karyawan PT. Puninar Jaya</div></div>
        </div>
        <div>
            <div class="eyebrow mb-3">Layanan Simpan Pinjam Digital</div>
            <h1 class="mb-3">Bersama membangun kesejahteraan anggota.</h1>
            <p class="mb-4" style="color:rgba(255,255,255,.7);max-width:440px">Pantau simpanan wajib, ajukan pinjaman, bayar angsuran, dan lihat pembagian SHU secara transparan sesuai ketentuan RAT.</p>
            <div class="d-flex flex-column gap-2 features">
                <div class="feature"><i class="fas fa-shield-halved"></i>Keputusan pinjaman berbasis aturan yang terukur</div>
                <div class="feature"><i class="fas fa-vault"></i>Simpanan wajib tercatat dan terverifikasi pengurus</div>
                <div class="feature"><i class="fas fa-coins"></i>Pembagian SHU terbuka untuk setiap anggota</div>
            </div>
        </div>
        <div class="small" style="color:rgba(255,255,255,.45)">&copy; {{ now()->year }} {{ config('koperasi.name') }}</div>
    </section>
    <section class="auth-panel">
        <div class="auth-box reveal">
            @include('partials.flash')
            @yield('content')
        </div>
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="{{ asset('js/hero3d.js') }}?v=3"></script>
</body>
</html>
