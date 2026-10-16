<?php
require_once 'includes/init.php';

// Require authentication and nasabah role
$auth->requireAuth();
$auth->requireRole('nasabah');

$user = $auth->getCurrentUser();

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = RECORDS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Get filter parameters
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build WHERE clause
$whereConditions = ['user_id = ?'];
$params = [$user['id']];

if (!empty($type)) {
    $whereConditions[] = 'type = ?';
    $params[] = $type;
}

if (!empty($status)) {
    $whereConditions[] = 'status = ?';
    $params[] = $status;
}

if (!empty($dateFrom)) {
    $whereConditions[] = 'DATE(transaction_date) >= ?';
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = 'DATE(transaction_date) <= ?';
    $params[] = $dateTo;
}

$whereClause = implode(' AND ', $whereConditions);

// Get transactions
$transactions = $db->select(
    "SELECT * FROM transactions WHERE {$whereClause} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}",
    $params
);

// Get total count for pagination
$totalRecords = $db->getCount('transactions', $whereClause, $params);
$totalPages = ceil($totalRecords / $limit);

$pageTitle = 'Transaksi - ' . APP_NAME;
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
                        <a class="nav-link" href="savings.php">
                            <i class="fas fa-piggy-bank me-1"></i> Tabungan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="loans.php">
                            <i class="fas fa-hand-holding-usd me-1"></i> Pinjaman
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="transactions.php">
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
                    <i class="fas fa-exchange-alt me-2"></i>
                    Riwayat Transaksi
                </h2>
                <p class="text-muted">Lihat semua transaksi Anda</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-filter me-2"></i>
                            Filter Transaksi
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="type" class="form-label">Jenis Transaksi</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">Semua Jenis</option>
                                    <option value="savings_deposit" <?php echo $type == 'savings_deposit' ? 'selected' : ''; ?>>Setor Tabungan</option>
                                    <option value="savings_withdrawal" <?php echo $type == 'savings_withdrawal' ? 'selected' : ''; ?>>Tarik Tabungan</option>
                                    <option value="loan_payment" <?php echo $type == 'loan_payment' ? 'selected' : ''; ?>>Bayar Angsuran</option>
                                    <option value="loan_disbursement" <?php echo $type == 'loan_disbursement' ? 'selected' : ''; ?>>Pencairan Pinjaman</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">Semua Status</option>
                                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Selesai</option>
                                    <option value="failed" <?php echo $status == 'failed' ? 'selected' : ''; ?>>Gagal</option>
                                    <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Dari Tanggal</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $dateFrom; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Sampai Tanggal</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $dateTo; ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i>
                                    Filter
                                </button>
                                <a href="transactions.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>
                                    Reset
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>
                            Daftar Transaksi
                        </h5>
                        <span class="badge bg-primary"><?php echo $totalRecords; ?> transaksi</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($transactions)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>Tidak ada transaksi ditemukan</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Jenis</th>
                                            <th>Jumlah</th>
                                            <th>Status</th>
                                            <th>Keterangan</th>
                                            <th>Referensi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td>
                                                    <?php echo formatDateIndo($transaction['transaction_date'], true); ?>
                                                </td>
                                                <td>
                                                    <i class="fas fa-<?php echo $transaction['type'] == 'savings_deposit' ? 'arrow-down text-success' : 'arrow-up text-danger'; ?> me-2"></i>
                                                    <?php echo ucwords(str_replace('_', ' ', $transaction['type'])); ?>
                                                </td>
                                                <td class="<?php echo $transaction['type'] == 'savings_deposit' ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo formatRupiah($transaction['amount']); ?>
                                                </td>
                                                <td><?php echo getStatusBadge($transaction['status'], 'transaction'); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['description'] ?? '-'); ?></td>
                                                <td>
                                                    <code><?php echo $transaction['reference_number']; ?></code>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div class="d-flex justify-content-center mt-4">
                                    <?php echo generatePagination($page, $totalPages, 'transactions.php', $_GET); ?>
                                </div>
                            <?php endif; ?>
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
