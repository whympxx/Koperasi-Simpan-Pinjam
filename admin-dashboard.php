<?php
/**
 * Admin Dashboard - PT. Puninar Jaya Logistik
 * Dashboard untuk administrator sistem simpan pinjam
 * 
 * @author System Developer
 * @version 1.0
 * @since 2024
 */

require_once 'includes/init.php';

// Require authentication and admin role
$auth->requireAuth();
$auth->requireRole(['admin', 'super_admin']);

$user = $auth->getCurrentUser();

// Get dashboard statistics
$statsQuery = "SELECT * FROM view_admin_dashboard";
$stats = $db->selectOne($statsQuery);

// Get pending loan applications
$pendingApplications = $db->select(
    "SELECT la.*, u.full_name, u.nik, u.phone, u.email, d.name as division_name 
     FROM loan_applications la 
     JOIN users u ON la.user_id = u.id 
     LEFT JOIN divisions d ON u.division_id = d.id 
     WHERE la.status = 'pending' 
     ORDER BY la.created_at DESC 
     LIMIT 10"
);

// Get recent transactions
$recentTransactions = $db->select(
    "SELECT t.*, u.full_name, u.nik 
     FROM transactions t 
     JOIN users u ON t.user_id = u.id 
     WHERE t.status = 'completed' 
     ORDER BY t.created_at DESC 
     LIMIT 15"
);

// Get overdue payments
$overduePayments = $db->select(
    "SELECT ls.*, u.full_name, u.nik, u.phone, al.loan_number 
     FROM loan_schedules ls 
     JOIN active_loans al ON ls.loan_id = al.id 
     JOIN users u ON al.user_id = u.id 
     WHERE ls.status = 'overdue' 
     ORDER BY ls.due_date ASC 
     LIMIT 10"
);

// Get system notifications
$systemNotifications = $db->select(
    "SELECT * FROM notifications 
     WHERE category = 'system' 
     ORDER BY created_at DESC 
     LIMIT 5"
);

// Get monthly statistics for charts
$monthlyStats = $db->select(
    "SELECT 
        DATE_FORMAT(transaction_date, '%Y-%m') as month,
        type,
        SUM(amount) as total_amount,
        COUNT(*) as transaction_count
     FROM transactions 
     WHERE status = 'completed' 
     AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
     GROUP BY DATE_FORMAT(transaction_date, '%Y-%m'), type
     ORDER BY month DESC"
);

// Get top borrowers by outstanding balance
$topBorrowers = $db->select(
    "SELECT u.full_name, u.nik, al.remaining_balance, al.monthly_installment, al.next_due_date
     FROM active_loans al 
     JOIN users u ON al.user_id = u.id 
     WHERE al.status = 'active' 
     ORDER BY al.remaining_balance DESC 
     LIMIT 10"
);

// Get recent user registrations
$recentUsers = $db->select(
    "SELECT u.*, d.name as division_name 
     FROM users u 
     LEFT JOIN divisions d ON u.division_id = d.id 
     WHERE u.role = 'nasabah' 
     ORDER BY u.created_at DESC 
     LIMIT 10"
);

$pageTitle = 'Admin Dashboard - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .admin-sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .admin-sidebar.collapsed {
            width: 70px;
        }
        
        .admin-sidebar .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .admin-sidebar .sidebar-nav {
            padding: 1rem 0;
        }
        
        .admin-sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            border-radius: 0;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .admin-sidebar .nav-link:hover,
        .admin-sidebar .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
            border-left-color: #fff;
        }
        
        .admin-sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .admin-main-content {
            margin-left: 250px;
            transition: all 0.3s ease;
        }
        
        .admin-main-content.expanded {
            margin-left: 70px;
        }
        
        .admin-topbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 2rem;
            margin-bottom: 2rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            overflow: hidden;
            position: relative;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-card .card-icon {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 2.5rem;
            opacity: 0.1;
        }
        
        .stats-gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stats-gradient-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            color: white;
        }
        
        .stats-gradient-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .stats-gradient-danger {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            color: white;
        }
        
        .stats-gradient-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        
        .stats-gradient-secondary {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            color: #333;
        }
        
        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: none;
        }
        
        .content-card .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }
        
        .table-responsive {
            border-radius: 0 0 15px 15px;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-overdue {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-active {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .sidebar-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
        }
        
        .quick-stats {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            
            .admin-sidebar.show {
                transform: translateX(0);
            }
            
            .admin-main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Admin Sidebar -->
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
                <li class="nav-item">
                    <a class="nav-link active" href="admin-dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin-users.php">
                        <i class="fas fa-users"></i>
                        <span>Manajemen User</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin-loans.php">
                        <i class="fas fa-hand-holding-usd"></i>
                        <span>Pengajuan Pinjaman</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin-active-loans.php">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Pinjaman Aktif</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin-savings.php">
                        <i class="fas fa-piggy-bank"></i>
                        <span>Akun Tabungan</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin-transactions.php">
                        <i class="fas fa-exchange-alt"></i>
                        <span>Transaksi</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin-reports.php">
                        <i class="fas fa-chart-bar"></i>
                        <span>Laporan</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin-shu.php">
                        <i class="fas fa-coins"></i>
                        <span>SHU</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="admin-settings.php">
                        <i class="fas fa-cog"></i>
                        <span>Pengaturan</span>
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-arrow-left"></i>
                        <span>Kembali ke User</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Keluar</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="admin-main-content" id="mainContent">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <button class="sidebar-toggle me-3" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h4 class="mb-0">Dashboard Administrator</h4>
                </div>
                
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <small class="text-muted">Selamat datang,</small><br>
                        <strong><?php echo htmlspecialchars($user['name'] ?? 'Admin'); ?></strong>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo ucfirst($user['role']); ?>
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

        <div class="container-fluid px-4">
            <!-- Quick Stats Overview -->
            <div class="quick-stats">
                <div class="row">
                    <div class="col-md-8">
                        <h2 class="mb-2">Selamat Datang di Admin Dashboard! 👋</h2>
                        <p class="mb-0 opacity-75">
                            Monitor dan kelola sistem simpan pinjam PT. Puninar Jaya Logistik
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex align-items-center justify-content-end">
                            <i class="fas fa-calendar me-2"></i>
                            <div>
                                <h6 class="mb-0"><?php echo formatDateIndo(date('Y-m-d'), true); ?></h6>
                                <small class="opacity-75"><?php echo date('H:i'); ?> WIB</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-2 mb-3">
                    <div class="stats-card stats-gradient-primary h-100">
                        <div class="card-body position-relative">
                            <h6 class="card-title text-white-50 mb-1">Pengajuan Menunggu</h6>
                            <h3 class="mb-0"><?php echo $stats['pending_applications'] ?? 0; ?></h3>
                            <small class="text-white-50">Pengajuan baru</small>
                            <i class="fas fa-clock card-icon"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-2 mb-3">
                    <div class="stats-card stats-gradient-success h-100">
                        <div class="card-body position-relative">
                            <h6 class="card-title text-white-50 mb-1">Pinjaman Aktif</h6>
                            <h3 class="mb-0"><?php echo $stats['active_loans'] ?? 0; ?></h3>
                            <small class="text-white-50">Pinjaman berjalan</small>
                            <i class="fas fa-hand-holding-usd card-icon"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-2 mb-3">
                    <div class="stats-card stats-gradient-warning h-100">
                        <div class="card-body position-relative">
                            <h6 class="card-title text-white-50 mb-1">Total Outstanding</h6>
                            <h3 class="mb-0"><?php echo formatRupiah($stats['total_outstanding'] ?? 0); ?></h3>
                            <small class="text-white-50">Pinjaman belum lunas</small>
                            <i class="fas fa-money-bill-wave card-icon"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-2 mb-3">
                    <div class="stats-card stats-gradient-info h-100">
                        <div class="card-body position-relative">
                            <h6 class="card-title text-white-50 mb-1">Total Tabungan</h6>
                            <h3 class="mb-0"><?php echo formatRupiah($stats['total_savings'] ?? 0); ?></h3>
                            <small class="text-white-50">Saldo tabungan</small>
                            <i class="fas fa-piggy-bank card-icon"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-2 mb-3">
                    <div class="stats-card stats-gradient-danger h-100">
                        <div class="card-body position-relative">
                            <h6 class="card-title text-white-50 mb-1">Tunggakan</h6>
                            <h3 class="mb-0"><?php echo $stats['overdue_payments'] ?? 0; ?></h3>
                            <small class="text-white-50">Pembayaran terlambat</small>
                            <i class="fas fa-exclamation-triangle card-icon"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-2 mb-3">
                    <div class="stats-card stats-gradient-secondary h-100">
                        <div class="card-body position-relative">
                            <h6 class="card-title mb-1">Total Nasabah</h6>
                            <h3 class="mb-0"><?php echo $db->getCount('users', 'role = ?', ['nasabah']); ?></h3>
                            <small class="text-muted">Pengguna aktif</small>
                            <i class="fas fa-users card-icon"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Row -->
            <div class="row">
                <!-- Pending Applications -->
                <div class="col-lg-6 mb-4">
                    <div class="content-card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-clock text-warning me-2"></i>
                                    Pengajuan Pinjaman Menunggu
                                </h5>
                                <a href="admin-loans.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($pendingApplications)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                                    <p>Tidak ada pengajuan yang menunggu persetujuan</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Nama</th>
                                                <th>NIK</th>
                                                <th>Jumlah</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pendingApplications as $app): ?>
                                                <tr>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($app['full_name']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($app['division_name'] ?? 'N/A'); ?></small>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($app['nik']); ?></td>
                                                    <td><?php echo formatRupiah($app['loan_amount']); ?></td>
                                                    <td>
                                                        <span class="status-badge status-pending">
                                                            <?php echo ucfirst($app['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="admin-loan-detail.php?id=<?php echo $app['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                            <a href="admin-loan-approve.php?id=<?php echo $app['id']; ?>" class="btn btn-outline-success btn-sm">
                                                                <i class="fas fa-check"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Overdue Payments -->
                <div class="col-lg-6 mb-4">
                    <div class="content-card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                                    Pembayaran Terlambat
                                </h5>
                                <a href="admin-overdue.php" class="btn btn-sm btn-outline-danger">Lihat Semua</a>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($overduePayments)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                                    <p>Tidak ada pembayaran yang terlambat</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Nama</th>
                                                <th>No. Pinjaman</th>
                                                <th>Jatuh Tempo</th>
                                                <th>Jumlah</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($overduePayments as $overdue): ?>
                                                <tr class="table-danger">
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($overdue['full_name']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($overdue['nik']); ?></small>
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($overdue['loan_number']); ?></td>
                                                    <td>
                                                        <?php echo formatDateIndo($overdue['due_date']); ?>
                                                        <br><small class="text-danger">Terlambat <?php echo abs(getDaysUntilDue($overdue['due_date'])); ?> hari</small>
                                                    </td>
                                                    <td><?php echo formatRupiah($overdue['total_amount']); ?></td>
                                                    <td>
                                                        <button class="btn btn-outline-danger btn-sm" onclick="sendReminder(<?php echo $overdue['id']; ?>)">
                                                            <i class="fas fa-bell"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts and Recent Activities -->
            <div class="row">
                <!-- Transaction Chart -->
                <div class="col-lg-8 mb-4">
                    <div class="content-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-line text-primary me-2"></i>
                                Grafik Transaksi 12 Bulan Terakhir
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="transactionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="col-lg-4 mb-4">
                    <div class="content-card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-exchange-alt text-success me-2"></i>
                                    Transaksi Terbaru
                                </h5>
                                <a href="admin-transactions.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentTransactions)): ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-inbox fa-2x mb-2"></i>
                                    <p>Belum ada transaksi</p>
                                </div>
                            <?php else: ?>
                                <?php foreach (array_slice($recentTransactions, 0, 8) as $transaction): ?>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="flex-shrink-0">
                                            <div class="bg-<?php echo $transaction['type'] == 'savings_deposit' ? 'success' : 'primary'; ?> rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="fas fa-<?php echo $transaction['type'] == 'savings_deposit' ? 'arrow-down' : 'arrow-up'; ?> text-white"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="fw-bold"><?php echo htmlspecialchars($transaction['full_name']); ?></div>
                                            <small class="text-muted"><?php echo ucwords(str_replace('_', ' ', $transaction['type'])); ?></small>
                                            <br><small class="text-muted"><?php echo formatDateIndo($transaction['transaction_date']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold <?php echo $transaction['type'] == 'savings_deposit' ? 'text-success' : 'text-primary'; ?>">
                                                <?php echo formatRupiah($transaction['amount']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Borrowers and Recent Users -->
            <div class="row">
                <!-- Top Borrowers -->
                <div class="col-lg-6 mb-4">
                    <div class="content-card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-trophy text-warning me-2"></i>
                                Top 10 Peminjam Terbesar
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($topBorrowers)): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-users fa-3x mb-3"></i>
                                    <p>Belum ada data peminjam</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Rank</th>
                                                <th>Nama</th>
                                                <th>Sisa Pinjaman</th>
                                                <th>Jatuh Tempo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($topBorrowers as $index => $borrower): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-<?php echo $index < 3 ? 'warning' : 'secondary'; ?> rounded-pill">
                                                            #<?php echo $index + 1; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($borrower['full_name']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($borrower['nik']); ?></small>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo formatRupiah($borrower['remaining_balance']); ?></strong>
                                                        <br><small class="text-muted">Angsuran: <?php echo formatRupiah($borrower['monthly_installment']); ?></small>
                                                    </td>
                                                    <td>
                                                        <span class="<?php echo isOverdue($borrower['next_due_date']) ? 'text-danger' : 'text-success'; ?>">
                                                            <?php echo formatDateIndo($borrower['next_due_date']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="col-lg-6 mb-4">
                    <div class="content-card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-user-plus text-info me-2"></i>
                                    Nasabah Terbaru
                                </h5>
                                <div class="d-flex gap-2">
                                    <a href="admin-users.php?register=1#daftar-nasabah" class="btn btn-sm btn-success">
                                        <i class="fas fa-user-plus me-1"></i> Daftarkan
                                    </a>
                                    <a href="admin-users.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentUsers)): ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <p>Belum ada nasabah baru</p>
                                </div>
                            <?php else: ?>
                                <?php foreach (array_slice($recentUsers, 0, 6) as $newUser): ?>
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="flex-shrink-0">
                                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="fas fa-user text-white"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="fw-bold"><?php echo htmlspecialchars($newUser['full_name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($newUser['division_name'] ?? 'N/A'); ?></small>
                                            <br><small class="text-muted">Bergabung: <?php echo timeAgo($newUser['created_at']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?php echo $newUser['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($newUser['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        }

        // Transaction Chart
        const ctx = document.getElementById('transactionChart').getContext('2d');
        
        // Prepare chart data from PHP
        const monthlyData = <?php echo json_encode($monthlyStats); ?>;
        
        // Group data by month and type
        const chartData = {};
        monthlyData.forEach(item => {
            if (!chartData[item.month]) {
                chartData[item.month] = {
                    month: item.month,
                    savings_deposit: 0,
                    savings_withdrawal: 0,
                    loan_disbursement: 0,
                    loan_payment: 0
                };
            }
            chartData[item.month][item.type] = item.total_amount;
        });

        const months = Object.keys(chartData).sort();
        const savingsDepositData = months.map(month => chartData[month].savings_deposit);
        const savingsWithdrawalData = months.map(month => chartData[month].savings_withdrawal);
        const loanDisbursementData = months.map(month => chartData[month].loan_disbursement);
        const loanPaymentData = months.map(month => chartData[month].loan_payment);

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: months.map(month => {
                    const date = new Date(month + '-01');
                    return date.toLocaleDateString('id-ID', { month: 'short', year: 'numeric' });
                }),
                datasets: [
                    {
                        label: 'Setoran Tabungan',
                        data: savingsDepositData,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Penarikan Tabungan',
                        data: savingsWithdrawalData,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Pencairan Pinjaman',
                        data: loanDisbursementData,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'Pembayaran Pinjaman',
                        data: loanPaymentData,
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': Rp ' + context.parsed.y.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });

        // Send reminder function
        function sendReminder(scheduleId) {
            if (confirm('Kirim pengingat pembayaran ke nasabah?')) {
                fetch('ajax/send-payment-reminder.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        schedule_id: scheduleId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Pengingat berhasil dikirim!');
                        location.reload();
                    } else {
                        alert('Gagal mengirim pengingat: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat mengirim pengingat');
                });
            }
        }

        // Auto refresh every 5 minutes
        setInterval(() => {
            location.reload();
        }, 300000);

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Welcome animation
        document.addEventListener('DOMContentLoaded', function() {
            const statsCards = document.querySelectorAll('.stats-card');
            statsCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });
        });
    </script>
</body>
</html>
