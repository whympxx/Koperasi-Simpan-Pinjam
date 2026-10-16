<?php
require_once 'includes/init.php';

$auth->requireAuth();
$auth->requireRole('nasabah');

$user = $auth->getCurrentUser();
$savingsManager = new SavingsManager();

$error = '';
$success = '';

// Pastikan akun simpanan wajib tersedia
$savingsAccount = $dbHelper->getSavingsAccountByUserId($user['id']);
if (!$savingsAccount) {
    $created = $savingsManager->createAccount($user['id']);
    if (!$created['success'] && strpos($created['message'] ?? '', 'sudah ada') === false) {
        $_SESSION['error_message'] = $created['message'] ?? 'Gagal membuat akun simpanan.';
        header('Location: savings.php');
        exit;
    }
    $savingsAccount = $dbHelper->getSavingsAccountByUserId($user['id']);
}

if ($_POST) {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!$auth->verifyCSRFToken($csrfToken)) {
        $error = 'Token keamanan tidak valid.';
    } else {
        $amount = (float)str_replace(['.', ','], ['', '.'], SecurityHelper::sanitize($_POST['amount'] ?? ''));
        $description = SecurityHelper::sanitize($_POST['description'] ?? '');
        $paymentMethod = SecurityHelper::sanitize($_POST['payment_method'] ?? 'transfer');

        if ($amount <= 0) {
            $error = 'Jumlah setoran harus lebih dari 0.';
        } elseif ($amount < 1000) {
            $error = 'Jumlah setoran minimal Rp 1.000.';
        } elseif ($amount > 50000000) {
            $error = 'Jumlah setoran maksimal Rp 50.000.000.';
        } else {
            $result = $savingsManager->deposit($user['id'], $amount, $paymentMethod, $description ?: 'Setoran simpanan wajib');
            if (!empty($result['success'])) {
                $success = $result['message'];
                $savingsAccount = $dbHelper->getSavingsAccountByUserId($user['id']);
                $_POST = [];
            } else {
                $error = $result['message'] ?? 'Setoran gagal diproses.';
            }
        }
    }
}

$pageTitle = 'Setor Simpanan Wajib - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="fas fa-building"></i> <strong><?php echo APP_NAME; ?></strong></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-1"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="savings.php"><i class="fas fa-piggy-bank me-1"></i> Simpanan</a></li>
                    <li class="nav-item"><a class="nav-link" href="loans.php"><i class="fas fa-hand-holding-usd me-1"></i> Pinjaman</a></li>
                    <li class="nav-item"><a class="nav-link" href="transactions.php"><i class="fas fa-exchange-alt me-1"></i> Transaksi</a></li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown"><i class="fas fa-user-circle me-1"></i><?php echo htmlspecialchars($user['name']); ?></a>
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

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="text-center mb-4">
                    <h2 class="text-gradient"><i class="fas fa-plus-circle me-2"></i>Setor Simpanan Wajib</h2>
                    <p class="text-muted">Saldo saat ini: <strong><?php echo formatRupiah($savingsAccount['balance'] ?? 0); ?></strong></p>
                </div>

                <div class="card">
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST" id="depositForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                            <div class="mb-3">
                                <label class="form-label">Jumlah Setoran</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input type="text" class="form-control" id="amount" name="amount" required
                                           value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>"
                                           placeholder="Contoh: 100.000">
                                </div>
                                <div class="form-text">Minimal Rp 1.000 · Maksimal Rp 50.000.000</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Metode</label>
                                <select class="form-select" name="payment_method">
                                    <option value="transfer">Transfer</option>
                                    <option value="cash">Tunai</option>
                                    <option value="payroll_deduction">Potongan Gaji</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Keterangan (opsional)</label>
                                <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="alert alert-info small">
                                Simpanan wajib bersifat beku selama masih anggota. Saldo ini menjadi dasar plafon pinjaman (1x/2x).
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success"><i class="fas fa-plus me-2"></i>Setor Sekarang</button>
                                <a href="savings.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Kembali</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('amount').addEventListener('input', function(e) {
            let v = e.target.value.replace(/[^0-9]/g, '');
            e.target.value = v ? new Intl.NumberFormat('id-ID').format(parseInt(v, 10)) : '';
        });
    </script>
</body>
</html>
