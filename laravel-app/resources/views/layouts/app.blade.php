<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · {{ config('koperasi.short_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/koppuja.css') }}?v=5" rel="stylesheet">
    @stack('styles')
</head>
<body>
@php
    $me = auth()->user();
    $unreadCount = $me->appNotifications()->where('is_read', false)->count();
    $pendingVerif = $me->isAdmin() ? \App\Models\Transaction::where('status', 'pending')->count() : 0;
    $pendingApps = $me->isAdmin() ? \App\Models\LoanApplication::whereIn('status', ['pending', 'reviewing'])->count() : 0;
    $menu = $me->isAdmin()
        ? [
            'Ringkasan' => [
                ['admin.dashboard', 'Dashboard', 'fa-chart-pie', 'admin.dashboard'],
                ['admin.verifications.index', 'Verifikasi', 'fa-circle-check', 'admin.verifications.*', $pendingVerif],
            ],
            'Keanggotaan' => [
                ['admin.users.index', 'Anggota', 'fa-users', 'admin.users.*'],
                ['admin.savings.index', 'Simpanan', 'fa-vault', 'admin.savings.*'],
                ['admin.payroll.index', 'Potong Gaji', 'fa-money-check-dollar', 'admin.payroll.*'],
                ['admin.announcements.index', 'Pengumuman', 'fa-bullhorn', 'admin.announcements.*'],
            ],
            'Pembiayaan' => [
                ['admin.loans.index', 'Pengajuan', 'fa-file-signature', 'admin.loans.*', $pendingApps],
                ['admin.active-loans.index', 'Pinjaman Aktif', 'fa-hand-holding-dollar', 'admin.active-loans.*'],
                ['admin.overdue.index', 'Tunggakan', 'fa-triangle-exclamation', 'admin.overdue.*'],
            ],
            'Pelaporan' => [
                ['admin.transactions.index', 'Transaksi', 'fa-right-left', 'admin.transactions.*'],
                ['admin.reports.index', 'Laporan Keuangan', 'fa-chart-column', 'admin.reports.*'],
                ['admin.shu.index', 'SHU', 'fa-coins', 'admin.shu.*'],
            ],
            'Sistem' => [
                ['admin.rules.index', 'Basis Aturan', 'fa-diagram-project', 'admin.rules.*'],
                ['admin.settings.index', 'Pengaturan', 'fa-sliders', 'admin.settings.*'],
                ['admin.activity-logs.index', 'Log Aktivitas', 'fa-clock-rotate-left', 'admin.activity-logs.*'],
            ],
        ]
        : [
            'Menu Anggota' => [
                ['dashboard', 'Beranda', 'fa-house', 'dashboard'],
                ['savings.index', 'Simpanan Wajib', 'fa-vault', 'savings.*'],
                ['loans.index', 'Pinjaman', 'fa-hand-holding-dollar', 'loans.*'],
                ['transactions.index', 'Riwayat Transaksi', 'fa-right-left', 'transactions.*'],
                ['shu.index', 'SHU Saya', 'fa-coins', 'shu.*'],
            ],
        ];
    $initials = collect(explode(' ', $me->full_name))->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('');
@endphp
<nav class="sidebar" id="sidebar" aria-label="Navigasi utama">
    <div class="brand">
        <div class="brand-emblem"><i class="fas fa-building-columns"></i></div>
        <div><div class="brand-name">{{ config('koperasi.short_name') }}</div><div class="brand-sub">Koperasi Karyawan PT. Puninar Jaya</div></div>
    </div>
    @foreach ($menu as $section => $items)
        <div class="section">{{ $section }}</div>
        @foreach ($items as $item)
            <a class="nav-link {{ request()->routeIs($item[3]) ? 'active' : '' }}" href="{{ route($item[0]) }}">
                <i class="fas {{ $item[2] }}"></i><span class="flex-grow-1">{{ $item[1] }}</span>
                @if (! empty($item[4]))<span class="badge bg-danger rounded-pill">{{ $item[4] }}</span>@endif
            </a>
        @endforeach
    @endforeach
    <div class="section">Akun</div>
    <a class="nav-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}" href="{{ route('notifications.index') }}"><i class="fas fa-bell"></i><span class="flex-grow-1">Notifikasi</span>
        @if ($unreadCount)<span class="badge bg-danger rounded-pill">{{ $unreadCount }}</span>@endif</a>
    <a class="nav-link {{ request()->routeIs('profile', 'password.*') ? 'active' : '' }}" href="{{ route('profile') }}"><i class="fas fa-id-card"></i><span>Profil & Kartu</span></a>
    <div class="sidebar-foot">&copy; {{ now()->year }} {{ config('koperasi.short_name') }} · Ketentuan RAT 2025</div>
</nav>

<div class="main">
    <header class="topbar">
        <button class="icon-btn d-lg-none" data-sidebar-toggle aria-label="Buka menu"><i class="fas fa-bars"></i></button>
        <div class="flex-grow-1 min-w-0">
            <div class="page-crumb text-truncate">{{ $me->isAdmin() ? 'Panel Pengurus' : 'Layanan Anggota' }} · {{ tanggal(now()) }}</div>
            <h1 class="page-title text-truncate">@yield('title', 'Dashboard')</h1>
        </div>
        <a href="{{ route('notifications.index') }}" class="icon-btn" title="Notifikasi" aria-label="Notifikasi">
            <i class="fas fa-bell"></i>
            @if ($unreadCount)<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $unreadCount }}</span>@endif
        </a>
        <div class="dropdown">
            <button class="user-chip border-0" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar">{{ $initials }}</span>
                <span class="d-none d-sm-block text-start lh-sm"><span class="fw-semibold small d-block">{{ $me->full_name }}</span><span class="text-muted" style="font-size:.7rem">{{ \App\Models\User::ROLES[$me->role] }}</span></span>
                <i class="fas fa-chevron-down small text-muted ms-1"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-2" style="border-radius:12px">
                <li><span class="dropdown-item-text small text-muted">NIK {{ $me->nik }}</span></li>
                <li><a class="dropdown-item rounded" href="{{ route('profile') }}"><i class="fas fa-id-card me-2"></i>Profil & Kartu Anggota</a></li>
                <li><a class="dropdown-item rounded" href="{{ route('password.edit') }}"><i class="fas fa-key me-2"></i>Ubah Password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item rounded text-danger"><i class="fas fa-right-from-bracket me-2"></i>Keluar</button></form></li>
            </ul>
        </div>
    </header>

    <main class="content">
        @include('partials.flash')
        @yield('content')
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/koppuja.js') }}?v=2"></script>
@stack('scripts')
</body>
</html>
