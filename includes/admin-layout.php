<?php
/**
 * Shared Admin Layout
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 *
 * Provides a consistent sidebar, topbar and page shell for all admin pages.
 */

if (!defined('APP_ACCESS')) {
    die('Direct access not allowed');
}

/**
 * Render the admin page header (opening HTML, sidebar, topbar and main content wrapper).
 *
 * @param string $pageTitle  Browser/page title
 * @param string $activePage Current script filename used to highlight the active menu
 * @param array  $user       Current user (from Auth::getCurrentUser())
 * @param string $heading    Heading shown in the topbar
 */
function renderAdminHeader($pageTitle, $activePage, $user, $heading = '') {
    $heading = $heading ?: $pageTitle;
    $displayName = $user['name'] ?? ($user['full_name'] ?? 'Admin');
    $role = $user['role'] ?? 'admin';

    $menu = [
        'admin-dashboard.php'   => ['Dashboard', 'fa-tachometer-alt'],
        'admin-users.php'       => ['Manajemen User', 'fa-users'],
        'admin-loans.php'       => ['Pengajuan Pinjaman', 'fa-hand-holding-usd'],
        'admin-active-loans.php'=> ['Pinjaman Aktif', 'fa-clipboard-list'],
        'admin-savings.php'     => ['Akun Tabungan', 'fa-piggy-bank'],
        'admin-transactions.php'=> ['Transaksi', 'fa-exchange-alt'],
        'admin-reports.php'     => ['Laporan', 'fa-chart-bar'],
        'admin-shu.php'         => ['SHU', 'fa-coins'],
        'admin-rules.php'       => ['Basis Aturan', 'fa-project-diagram'],
        'admin-settings.php'    => ['Pengaturan', 'fa-cog'],
    ];
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .admin-sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh; position: fixed; left: 0; top: 0; width: 250px; z-index: 1000;
            transition: all 0.3s ease;
        }
        .admin-sidebar.collapsed { width: 70px; }
        .admin-sidebar.collapsed .sidebar-header small,
        .admin-sidebar.collapsed .nav-link span { display: none; }
        .admin-sidebar .sidebar-header { padding: 1.5rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.1); color:#fff; }
        .admin-sidebar .sidebar-nav { padding: 1rem 0; }
        .admin-sidebar .nav-link {
            color: rgba(255,255,255,0.8); padding: 0.75rem 1.5rem; border-left: 3px solid transparent;
            transition: all 0.3s ease;
        }
        .admin-sidebar .nav-link:hover, .admin-sidebar .nav-link.active {
            color: #fff; background-color: rgba(255,255,255,0.1); border-left-color: #fff;
        }
        .admin-sidebar .nav-link i { width: 20px; margin-right: 10px; text-align:center; }
        .admin-main-content { margin-left: 250px; transition: all 0.3s ease; }
        .admin-main-content.expanded { margin-left: 70px; }
        .admin-topbar { background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 1rem 2rem; margin-bottom: 2rem; }
        .sidebar-toggle { background: none; border: none; color: #333; font-size: 1.2rem; cursor: pointer; }
        .stats-card { background: #fff; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); transition: transform 0.3s ease; overflow: hidden; position: relative; }
        .stats-card:hover { transform: translateY(-5px); }
        .stats-card .card-icon { position: absolute; right: 20px; top: 20px; font-size: 2.5rem; opacity: 0.15; }
        .stats-gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; }
        .stats-gradient-success { background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%); color: #fff; }
        .stats-gradient-warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: #fff; }
        .stats-gradient-danger { background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); color: #fff; }
        .stats-gradient-info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: #fff; }
        .content-card { background: #fff; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); border: none; }
        .content-card .card-header { background: #fff; border-bottom: 1px solid #eee; border-radius: 15px 15px 0 0 !important; padding: 1.25rem 1.5rem; }
        @media (max-width: 768px) {
            .admin-sidebar { transform: translateX(-100%); }
            .admin-sidebar.show { transform: translateX(0); }
            .admin-main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <nav class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <div class="d-flex align-items-center">
                <i class="fas fa-building fa-2x me-3"></i>
                <div>
                    <h5 class="mb-0">Admin Panel</h5>
                    <small class="opacity-75">PT. Puninar Jaya</small>
                </div>
            </div>
        </div>
        <div class="sidebar-nav">
            <ul class="nav flex-column">
                <?php foreach ($menu as $file => $item): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $activePage === $file ? 'active' : ''; ?>" href="<?php echo $file; ?>">
                            <i class="fas <?php echo $item[1]; ?>"></i>
                            <span><?php echo $item[0]; ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
                <li class="nav-item mt-3">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-arrow-left"></i><span>Kembali ke User</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i><span>Keluar</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="admin-main-content" id="mainContent">
        <div class="admin-topbar">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <button class="sidebar-toggle me-3" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                    <h4 class="mb-0"><?php echo htmlspecialchars($heading); ?></h4>
                </div>
                <div class="d-flex align-items-center">
                    <div class="me-3 text-end">
                        <small class="text-muted">Selamat datang,</small><br>
                        <strong><?php echo htmlspecialchars($displayName); ?></strong>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo ucfirst($role); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profil</a></li>
                            <li><a class="dropdown-item" href="change-password.php"><i class="fas fa-key me-2"></i> Ubah Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Keluar</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="container-fluid px-4 pb-5">
    <?php
}

/**
 * Render the admin page footer (closing wrappers and scripts).
 */
function renderAdminFooter() {
    ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('adminSidebar').classList.toggle('collapsed');
            document.getElementById('mainContent').classList.toggle('expanded');
        }
    </script>
</body>
</html>
    <?php
}
