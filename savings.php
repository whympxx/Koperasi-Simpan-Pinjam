<?php
require_once 'includes/init.php';

// Require authentication and nasabah role
$auth->requireAuth();
$auth->requireRole('nasabah');

$user = $auth->getCurrentUser();
$savingsManager = new SavingsManager();

// Pastikan akun simpanan wajib ada
$savingsAccount = $dbHelper->getSavingsAccountByUserId($user['id']);
if (!$savingsAccount) {
    $savingsManager->createAccount($user['id']);
    $savingsAccount = $dbHelper->getSavingsAccountByUserId($user['id']);
}

// Ringkasan mutasi dari transaksi
$depositSum = $db->selectOne(
    "SELECT COALESCE(SUM(amount),0) AS total FROM transactions
     WHERE user_id = ? AND type IN ('savings_deposit','interest_credit') AND status = 'completed'",
    [$user['id']]
);
$withdrawSum = $db->selectOne(
    "SELECT COALESCE(SUM(amount),0) AS total FROM transactions
     WHERE user_id = ? AND type = 'savings_withdrawal' AND status IN ('completed','pending')",
    [$user['id']]
);
if ($savingsAccount) {
    $savingsAccount['total_deposits'] = (float)($depositSum['total'] ?? 0);
    $savingsAccount['total_withdrawals'] = (float)($withdrawSum['total'] ?? 0);
}

// Get savings transactions
$savingsTransactions = $db->select(
    "SELECT * FROM transactions WHERE user_id = ? AND type IN ('savings_deposit', 'savings_withdrawal') ORDER BY created_at DESC",
    [$user['id']]
);

$pageTitle = 'Simpanan Wajib - ' . APP_NAME;
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
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="savings.php">
                            <i class="fas fa-piggy-bank me-1"></i> Tabungan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="loans.php">
                            <i class="fas fa-hand-holding-usd me-1"></i> Pinjaman
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transactions.php">
                            <i class="fas fa-exchange-alt me-1"></i> Transaksi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shu.php">
                            <i class="fas fa-coins me-1"></i> SHU
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
                    <i class="fas fa-piggy-bank me-2"></i>
                    Simpanan Wajib
                </h2>
                <p class="text-muted">Simpanan wajib bersifat beku selama masih menjadi anggota koperasi</p>
                <?php if (!empty($_SESSION['error_message'])): ?>
                    <div class="alert alert-warning"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
                <?php endif; ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    Sesuai ketentuan KOPPUJA RAT 2025: simpanan wajib tidak dapat diambil kecuali keluar keanggotaan.
                    Pelayanan Simpanan Sukarela telah ditiadakan. Plafon pinjaman dihitung dari saldo simpanan wajib.
                </div>
            </div>
        </div>

        <!-- Savings Summary -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card summary-card card-gradient-success">
                    <div class="card-body position-relative">
                        <h6 class="card-title text-white-50">Saldo Simpanan Wajib</h6>
                        <h3 class="mb-0"><?php echo formatRupiah($savingsAccount['balance'] ?? 0); ?></h3>
                        <p class="mb-0 text-white-50">
                            <small>Minimal <?php echo formatRupiah(MIN_MANDATORY_SAVINGS); ?> · Status beku</small>
                        </p>
                        <i class="fas fa-piggy-bank card-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card summary-card card-gradient-primary">
                    <div class="card-body position-relative">
                        <h6 class="card-title text-white-50">Total Setoran</h6>
                        <h3 class="mb-0"><?php echo formatRupiah($savingsAccount['total_deposits'] ?? 0); ?></h3>
                        <p class="mb-0 text-white-50">
                            <small>Sejak bergabung</small>
                        </p>
                        <i class="fas fa-arrow-down card-icon"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card summary-card card-gradient-warning">
                    <div class="card-body position-relative">
                        <h6 class="card-title text-white-50">Total Penarikan</h6>
                        <h3 class="mb-0"><?php echo formatRupiah($savingsAccount['total_withdrawals'] ?? 0); ?></h3>
                        <p class="mb-0 text-white-50">
                            <small>Sejak bergabung</small>
                        </p>
                        <i class="fas fa-arrow-up card-icon"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-bolt text-primary me-2"></i>
                            Aksi Cepat
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <a href="savings-deposit.php" class="btn btn-success w-100">
                                    <i class="fas fa-plus me-2"></i>
                                    Setor Simpanan Wajib
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <button type="button" class="btn btn-outline-secondary w-100" disabled title="Simpanan wajib beku selama masih anggota">
                                    <i class="fas fa-lock me-2"></i>
                                    Simpanan Beku
                                </button>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="savings-statement.php" class="btn btn-info w-100">
                                    <i class="fas fa-file-alt me-2"></i>
                                    Laporan Tabungan
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="transactions.php" class="btn btn-primary w-100">
                                    <i class="fas fa-history me-2"></i>
                                    Riwayat Transaksi
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-exchange-alt text-success me-2"></i>
                            Transaksi Tabungan Terbaru
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($savingsTransactions)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>Belum ada transaksi tabungan</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Jenis Transaksi</th>
                                            <th>Jumlah</th>
                                            <th>Status</th>
                                            <th>Keterangan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($savingsTransactions as $transaction): ?>
                                            <tr>
                                                <td><?php echo formatDateIndo($transaction['transaction_date']); ?></td>
                                                <td>
                                                    <i class="fas fa-<?php echo $transaction['type'] == 'savings_deposit' ? 'arrow-down text-success' : 'arrow-up text-danger'; ?> me-2"></i>
                                                    <?php echo ucwords(str_replace('_', ' ', $transaction['type'])); ?>
                                                </td>
                                                <td class="<?php echo $transaction['type'] == 'savings_deposit' ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo formatRupiah($transaction['amount']); ?>
                                                </td>
                                                <td><?php echo getStatusBadge($transaction['status'], 'transaction'); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['description'] ?? '-'); ?></td>
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
