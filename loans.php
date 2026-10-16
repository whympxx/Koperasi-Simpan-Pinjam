<?php
require_once 'includes/init.php';

// Require authentication and nasabah role
$auth->requireAuth();
$auth->requireRole('nasabah');

$user = $auth->getCurrentUser();
$loanManager = new LoanManager();

// Get user's loan applications
$loanApplications = $db->select(
    "SELECT * FROM loan_applications WHERE user_id = ? ORDER BY created_at DESC",
    [$user['id']]
);

// Get active loan
$activeLoan = $dbHelper->getActiveLoanByUserId($user['id']);

// Get loan schedules if has active loan
$loanSchedules = [];
if ($activeLoan) {
    $loanSchedules = $db->select(
        "SELECT * FROM loan_schedules WHERE loan_id = ? ORDER BY installment_number ASC",
        [$activeLoan['id']]
    );
}

// Check eligibility for new loan
$loanEligibility = canApplyLoan($user, $dbHelper);

$pageTitle = 'Pinjaman - ' . APP_NAME;
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
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
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
                        <a class="nav-link <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'savings.php' ? 'active' : ''; ?>" href="savings.php">
                            <i class="fas fa-piggy-bank me-1"></i> Tabungan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'loans.php' ? 'active' : ''; ?>" href="loans.php" aria-current="page">
                            <i class="fas fa-hand-holding-usd me-1"></i> Pinjaman
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shu.php">
                            <i class="fas fa-coins me-1"></i> SHU
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $currentPage === 'transactions.php' ? 'active' : ''; ?>" href="transactions.php">
                            <i class="fas fa-exchange-alt me-1"></i> Transaksi
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
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
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="text-gradient">
                    <i class="fas fa-hand-holding-usd me-2"></i>
                    Pinjaman
                </h2>
                <p class="text-muted">Kelola pinjaman Anda</p>
            </div>
        </div>

        <?php if ($activeLoan): ?>
            <!-- Active Loan Info -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card card-gradient-warning">
                        <div class="card-body">
                            <h5 class="card-title text-white">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Pinjaman Aktif
                            </h5>
                            <div class="row">
                                <div class="col-md-3">
                                    <h6 class="text-white-50">Sisa Pokok</h6>
                                    <h4 class="text-white"><?php echo formatRupiah($activeLoan['remaining_balance']); ?></h4>
                                </div>
                                <div class="col-md-3">
                                    <h6 class="text-white-50">Angsuran Bulanan</h6>
                                    <h4 class="text-white"><?php echo formatRupiah($activeLoan['monthly_installment']); ?></h4>
                                </div>
                                <div class="col-md-3">
                                    <h6 class="text-white-50">Sisa Angsuran</h6>
                                    <h4 class="text-white"><?php echo (int)($activeLoan['remaining_months'] ?? $activeLoan['remaining_installments'] ?? 0); ?> bulan</h4>
                                </div>
                                <div class="col-md-3">
                                    <h6 class="text-white-50">Status</h6>
                                    <h4 class="text-white"><?php echo getStatusBadge($activeLoan['status']); ?></h4>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="loan-payment.php" class="btn btn-light">
                                    <i class="fas fa-credit-card me-2"></i>
                                    Bayar Angsuran
                                </a>
                                <a href="loans.php#jadwal" class="btn btn-outline-light">
                                    <i class="fas fa-calendar me-2"></i>
                                    Jadwal Pembayaran
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- No Active Loan -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-hand-holding-usd fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">Tidak Ada Pinjaman Aktif</h4>
                            <p class="text-muted">Anda belum memiliki pinjaman aktif saat ini.</p>
                            <?php if ($loanEligibility['can_apply']): ?>
                                <a href="apply-loan.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>
                                    Ajukan Pinjaman Baru
                                </a>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Info:</strong> <?php echo $loanEligibility['reason']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($activeLoan && !empty($loanSchedules)): ?>
            <div class="row mb-4" id="jadwal">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0"><i class="fas fa-calendar text-primary me-2"></i> Jadwal Angsuran</h5></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Jatuh Tempo</th>
                                            <th>Jumlah</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($loanSchedules as $schedule): ?>
                                            <tr>
                                                <td><?php echo (int)$schedule['installment_number']; ?></td>
                                                <td><?php echo formatDateIndo($schedule['due_date']); ?></td>
                                                <td><?php echo formatRupiah($schedule['total_amount']); ?></td>
                                                <td><?php echo getStatusBadge($schedule['status'], 'payment'); ?></td>
                                                <td>
                                                    <?php if ($schedule['status'] !== 'paid'): ?>
                                                        <a href="loan-payment.php?schedule_id=<?php echo (int)$schedule['id']; ?>" class="btn btn-sm btn-outline-primary">Bayar</a>
                                                    <?php else: ?>
                                                        <span class="text-muted small">Lunas</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Loan History -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-history text-info me-2"></i>
                            Riwayat Pengajuan
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($loanApplications)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>Belum ada riwayat pengajuan pinjaman</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>No. Pengajuan</th>
                                            <th>Tanggal</th>
                                            <th>Jumlah</th>
                                            <th>Tenor</th>
                                            <th>Angsuran</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($loanApplications as $loan): ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($loan['application_number']); ?></code></td>
                                                <td><?php echo formatDateIndo($loan['created_at']); ?></td>
                                                <td><?php echo formatRupiah($loan['loan_amount']); ?></td>
                                                <td><?php echo (int)$loan['loan_term_months']; ?> bulan</td>
                                                <td><?php echo formatRupiah($loan['monthly_installment']); ?></td>
                                                <td><?php echo getStatusBadge($loan['status']); ?></td>
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
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
