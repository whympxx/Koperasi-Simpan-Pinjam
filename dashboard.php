<?php
require_once 'includes/init.php';

// Require authentication and nasabah role
$auth->requireAuth();
$auth->requireRole('nasabah');

$user = $auth->getCurrentUser();

// Pastikan akun simpanan wajib tersedia agar plafon & ringkasan tidak kosong
$savingsManager = new SavingsManager();
$savingsAccount = $dbHelper->getSavingsAccountByUserId($user['id']);
if (!$savingsAccount) {
    $savingsManager->createAccount($user['id']);
    $savingsAccount = $dbHelper->getSavingsAccountByUserId($user['id']);
}

// Get user dashboard data
$dashboardQuery = "SELECT * FROM view_nasabah_dashboard WHERE user_id = ?";
$dashboardData = $db->selectOne($dashboardQuery, [$user['id']]);

// Get active loan
$activeLoan = $dbHelper->getActiveLoanByUserId($user['id']);

// Get recent transactions
$recentTransactions = $db->select(
    "SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
    [$user['id']]
);

// Get notifications
$notifications = $db->select(
    "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5",
    [$user['id']]
);

// Get loan eligibility
$loanEligibility = canApplyLoan($user, $dbHelper);

$shuManager = new SHUManager();
$shuYear = (int)date('Y');
$shuShare = $shuManager->getMemberShare($user['id'], $shuYear);

$pageTitle = 'Dashboard - ' . APP_NAME;
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
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .navbar-brand img {
            width: 40px;
            height: 40px;
            margin-right: 10px;
        }
        
        .summary-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
            overflow: hidden;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
        }
        
        .card-gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .card-gradient-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
            color: white;
        }
        
        .card-gradient-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .card-gradient-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        
        .card-icon {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 2.5rem;
            opacity: 0.3;
        }
        
        .quick-action-btn {
            border-radius: 10px;
            padding: 15px;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .notification-item {
            border-left: 4px solid #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .transaction-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        
        .transaction-item:last-child {
            border-bottom: none;
        }
        
        .badge-transaction {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .weather-widget {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 1rem;
            backdrop-filter: blur(10px);
        }
        
        @media (max-width: 768px) {
            .summary-card {
                margin-bottom: 1rem;
            }
            
            .welcome-section {
                padding: 1.5rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-building"></i>
                <strong><?php echo APP_NAME; ?></strong>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php" aria-current="page">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'savings.php' ? 'active' : ''; ?>" href="savings.php">
                            <i class="fas fa-piggy-bank me-1"></i> Tabungan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'loans.php' ? 'active' : ''; ?>" href="loans.php">
                            <i class="fas fa-hand-holding-usd me-1"></i> Pinjaman
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'transactions.php' ? 'active' : ''; ?>" href="transactions.php">
                            <i class="fas fa-exchange-alt me-1"></i> Transaksi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'shu.php' ? 'active' : ''; ?>" href="shu.php">
                            <i class="fas fa-coins me-1"></i> SHU
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <?php if (count($notifications) > 0): ?>
                                <span class="badge bg-danger rounded-pill"><?php echo count($notifications); ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if (empty($notifications)): ?>
                                <li class="dropdown-item text-muted">Tidak ada notifikasi</li>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <li>
                                        <a class="dropdown-item" href="#" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                            <small class="text-muted"><?php echo timeAgo($notification['created_at']); ?></small><br>
                                            <strong><?php echo htmlspecialchars($notification['title']); ?></strong><br>
                                            <span class="text-muted"><?php echo truncateText($notification['message'], 50); ?></span>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-center" href="notifications.php">Lihat Semua</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo $user['name']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profil</a></li>
                            <li><a class="dropdown-item" href="change-password.php"><i class="fas fa-key me-2"></i> Ubah Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Keluar</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">Selamat Datang, <?php echo explode(' ', $user['name'])[0]; ?>! 👋</h2>
                    <p class="mb-0 opacity-75">
                        NIK: <?php echo $user['nik']; ?> | <?php echo $user['position']; ?> - <?php echo $user['division']; ?>
                    </p>
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-calendar me-2"></i>
                        <?php echo formatDateIndo(date('Y-m-d'), true); ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="weather-widget">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-building me-2 fa-2x"></i>
                            <div>
                                <h6 class="mb-0">PT. Puninar Jaya</h6>
                                <small>Cakung, Jakarta Timur</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Financial Summary Cards -->
            <div class="col-md-3 mb-4">
                <div class="card summary-card card-gradient-success h-100">
                    <div class="card-body position-relative">
                        <h6 class="card-title text-white-50">Saldo Simpanan Wajib</h6>
                        <h3 class="mb-0"><?php echo formatRupiah($savingsAccount['balance'] ?? 0); ?></h3>
                        <p class="mb-0 text-white-50">
                            <small>Dasar plafon pinjaman · status beku</small>
                        </p>
                        <i class="fas fa-piggy-bank card-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-4">
                <div class="card summary-card card-gradient-warning h-100">
                    <div class="card-body position-relative">
                        <h6 class="card-title text-white-50">Pinjaman Aktif</h6>
                        <h3 class="mb-0"><?php echo formatRupiah($activeLoan['remaining_balance'] ?? 0); ?></h3>
                        <p class="mb-0 text-white-50">
                            <small>
                                <?php if ($activeLoan): ?>
                                    Angsuran: <?php echo formatRupiah($activeLoan['monthly_installment']); ?>
                                <?php else: ?>
                                    Tidak ada pinjaman aktif
                                <?php endif; ?>
                            </small>
                        </p>
                        <i class="fas fa-hand-holding-usd card-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-4">
                <div class="card summary-card card-gradient-primary h-100">
                    <div class="card-body position-relative">
                        <h6 class="card-title text-white-50">Total Transaksi</h6>
                        <h3 class="mb-0"><?php echo count($recentTransactions); ?></h3>
                        <p class="mb-0 text-white-50">
                            <small>Transaksi bulan ini</small>
                        </p>
                        <i class="fas fa-exchange-alt card-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-4">
                <div class="card summary-card card-gradient-info h-100">
                    <div class="card-body position-relative">
                        <h6 class="card-title text-white-50">Limit Pinjaman</h6>
                        <h3 class="mb-0"><?php echo formatRupiah(getMaxLoanAmount($user, $savingsAccount['balance'] ?? 0, $dbHelper)); ?></h3>
                        <p class="mb-0 text-white-50">
                            <small><?php echo (int)getLoanMultiplierForUser($dbHelper->getUserById($user['id']) ?: $user, $dbHelper); ?>x simpanan wajib</small>
                        </p>
                        <i class="fas fa-credit-card card-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card summary-card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:#fff;">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h6 class="text-white-50 mb-1">Estimasi SHU Anda Tahun Buku <?php echo $shuYear; ?></h6>
                        <h3 class="mb-1"><?php echo formatRupiah($shuShare['shu_total']); ?></h3>
                        <p class="mb-0 opacity-75">
                            Jasa simpanan <?php echo formatRupiah($shuShare['shu_simpanan']); ?>
                            · Jasa pinjaman <?php echo formatRupiah($shuShare['shu_pinjaman']); ?>
                            · SHU koperasi <?php echo formatRupiah($shuShare['period']['shu_amount']); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-md-end mt-3 mt-md-0">
                        <a href="shu.php" class="btn btn-light">
                            <i class="fas fa-file-alt me-1"></i> Lihat Laporan SHU
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Quick Actions -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-bolt text-primary me-2"></i>
                            Aksi Cepat
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6 mb-3">
                                <a href="<?php echo $loanEligibility['can_apply'] ? 'apply-loan.php' : '#'; ?>" 
                                   class="quick-action-btn d-block text-center text-decoration-none bg-primary text-white <?php echo !$loanEligibility['can_apply'] ? 'disabled' : ''; ?>">
                                    <i class="fas fa-hand-holding-usd fa-2x mb-2"></i>
                                    <div class="fw-bold">Ajukan Pinjaman</div>
                                    <?php if (!$loanEligibility['can_apply']): ?>
                                        <small class="opacity-75">Tidak Tersedia</small>
                                    <?php endif; ?>
                                </a>
                            </div>
                            <div class="col-6 mb-3">
                                <a href="savings-deposit.php" class="quick-action-btn d-block text-center text-decoration-none bg-success text-white">
                                    <i class="fas fa-piggy-bank fa-2x mb-2"></i>
                                    <div class="fw-bold">Setor Simpanan</div>
                                </a>
                            </div>
                            <div class="col-6 mb-3">
                                <a href="transactions.php" class="quick-action-btn d-block text-center text-decoration-none bg-info text-white">
                                    <i class="fas fa-history fa-2x mb-2"></i>
                                    <div class="fw-bold">Riwayat Transaksi</div>
                                </a>
                            </div>
                            <div class="col-6 mb-3">
                                <?php if ($activeLoan): ?>
                                    <a href="loan-payment.php" class="quick-action-btn d-block text-center text-decoration-none bg-warning text-white">
                                        <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                        <div class="fw-bold">Bayar Angsuran</div>
                                    </a>
                                <?php else: ?>
                                    <a href="savings-deposit.php" class="quick-action-btn d-block text-center text-decoration-none bg-secondary text-white">
                                        <i class="fas fa-piggy-bank fa-2x mb-2"></i>
                                        <div class="fw-bold">Setor Simpanan</div>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if (!$loanEligibility['can_apply']): ?>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Info:</strong> <?php echo $loanEligibility['reason']; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-exchange-alt text-success me-2"></i>
                            Transaksi Terbaru
                        </h5>
                        <a href="transactions.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentTransactions)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>Belum ada transaksi</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentTransactions as $transaction): ?>
                                <div class="transaction-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-<?php echo $transaction['type'] == 'savings_deposit' ? 'arrow-down text-success' : 'arrow-up text-danger'; ?> me-2"></i>
                                                <div>
                                                    <div class="fw-bold"><?php echo ucwords(str_replace('_', ' ', $transaction['type'])); ?></div>
                                                    <small class="text-muted"><?php echo formatDateIndo($transaction['transaction_date']); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <div class="fw-bold <?php echo $transaction['type'] == 'savings_deposit' ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo formatRupiah($transaction['amount']); ?>
                                            </div>
                                            <?php echo getStatusBadge($transaction['status'], 'transaction'); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notifications & Loan Schedule -->
        <div class="row">
            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-calendar-alt text-info me-2"></i>
                            Jadwal Pembayaran
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($activeLoan): ?>
                            <?php
                            $schedules = $db->select(
                                "SELECT * FROM loan_schedules WHERE loan_id = ? AND status != 'paid' ORDER BY due_date ASC LIMIT 3",
                                [$activeLoan['id']]
                            );
                            ?>
                            
                            <?php if ($schedules): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Angsuran Ke</th>
                                                <th>Tanggal Jatuh Tempo</th>
                                                <th>Jumlah</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($schedules as $schedule): ?>
                                                <tr class="<?php echo isOverdue($schedule['due_date']) ? 'table-danger' : ''; ?>">
                                                    <td><?php echo $schedule['installment_number']; ?></td>
                                                    <td>
                                                        <?php echo formatDateIndo($schedule['due_date']); ?>
                                                        <?php if (isOverdue($schedule['due_date'])): ?>
                                                            <br><small class="text-danger">Terlambat <?php echo abs(getDaysUntilDue($schedule['due_date'])); ?> hari</small>
                                                        <?php else: ?>
                                                            <br><small class="text-muted"><?php echo getDaysUntilDue($schedule['due_date']); ?> hari lagi</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo formatRupiah($schedule['total_amount']); ?></td>
                                                    <td><?php echo getStatusBadge($schedule['status']); ?></td>
                                                    <td>
                                                        <?php if ($schedule['status'] == 'pending' || $schedule['status'] == 'overdue'): ?>
                                                            <a href="loan-payment.php?schedule_id=<?php echo $schedule['id']; ?>" class="btn btn-sm btn-primary">
                                                                Bayar
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    Tidak ada pembayaran yang jatuh tempo dalam waktu dekat.
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-calendar-times fa-3x mb-3"></i>
                                <p>Tidak ada pinjaman aktif</p>
                                <?php if ($loanEligibility['can_apply']): ?>
                                    <a href="apply-loan.php" class="btn btn-primary">Ajukan Pinjaman</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-bell text-warning me-2"></i>
                            Notifikasi
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($notifications)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-bell-slash fa-3x mb-3"></i>
                                <p>Tidak ada notifikasi baru</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item p-3 mb-2 rounded">
                                    <div class="d-flex align-items-start">
                                        <i class="<?php echo getNotificationIcon($notification['type']); ?> me-3 mt-1"></i>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <small class="text-muted"><?php echo timeAgo($notification['created_at']); ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Mark notification as read
        function markAsRead(notificationId) {
            fetch('ajax/mark-notification-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    notification_id: notificationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update notification count
                    location.reload();
                }
            });
        }

        // Auto refresh every 5 minutes
        setInterval(() => {
            location.reload();
        }, 300000);

        // Welcome animation
        document.addEventListener('DOMContentLoaded', function() {
            const summaryCards = document.querySelectorAll('.summary-card');
            summaryCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 150);
            });
        });
    </script>
</body>
</html>
