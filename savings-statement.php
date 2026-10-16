<?php
require_once 'includes/init.php';

$auth->requireAuth();
$auth->requireRole('nasabah');

$user = $auth->getCurrentUser();
$savingsManager = new SavingsManager();

$savingsAccount = $dbHelper->getSavingsAccountByUserId($user['id']);
if (!$savingsAccount) {
    $savingsManager->createAccount($user['id']);
    $savingsAccount = $dbHelper->getSavingsAccountByUserId($user['id']);
}

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$transactions = $db->select(
    "SELECT * FROM transactions
     WHERE user_id = ?
       AND type IN ('savings_deposit', 'savings_withdrawal', 'interest_credit')
       AND DATE(transaction_date) BETWEEN ? AND ?
     ORDER BY transaction_date DESC, id DESC",
    [$user['id'], $from, $to]
);

$totalDeposit = 0;
$totalWithdraw = 0;
foreach ($transactions as $tx) {
    if ($tx['type'] === 'savings_deposit' || $tx['type'] === 'interest_credit') {
        $totalDeposit += (float)$tx['amount'];
    } elseif ($tx['type'] === 'savings_withdrawal') {
        $totalWithdraw += (float)$tx['amount'];
    }
}

$pageTitle = 'Laporan Simpanan - ' . APP_NAME;
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
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="savings.php"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1"><i class="fas fa-file-alt me-2"></i>Laporan Simpanan Wajib</h2>
                <p class="text-muted mb-0">No. Rekening: <?php echo htmlspecialchars($savingsAccount['account_number'] ?? '-'); ?></p>
            </div>
            <button class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-1"></i> Cetak</button>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Dari Tanggal</label>
                        <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($from); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sampai Tanggal</label>
                        <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($to); ?>">
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter me-1"></i> Tampilkan</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4"><div class="card p-3"><small class="text-muted">Saldo Saat Ini</small><h4><?php echo formatRupiah($savingsAccount['balance'] ?? 0); ?></h4></div></div>
            <div class="col-md-4"><div class="card p-3"><small class="text-muted">Total Setoran (periode)</small><h4 class="text-success"><?php echo formatRupiah($totalDeposit); ?></h4></div></div>
            <div class="col-md-4"><div class="card p-3"><small class="text-muted">Total Penarikan (periode)</small><h4 class="text-danger"><?php echo formatRupiah($totalWithdraw); ?></h4></div></div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Mutasi Periode</strong></div>
            <div class="card-body p-0">
                <?php if (empty($transactions)): ?>
                    <div class="text-center text-muted py-5">Tidak ada mutasi pada periode ini.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Referensi</th>
                                    <th>Jenis</th>
                                    <th>Keterangan</th>
                                    <th class="text-end">Jumlah</th>
                                    <th class="text-end">Saldo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $tx): ?>
                                    <tr>
                                        <td><?php echo formatDateIndo($tx['transaction_date']); ?></td>
                                        <td><code><?php echo htmlspecialchars($tx['reference_number']); ?></code></td>
                                        <td>
                                            <?php
                                            $labels = [
                                                'savings_deposit' => 'Setoran',
                                                'savings_withdrawal' => 'Penarikan',
                                                'interest_credit' => 'Bunga'
                                            ];
                                            echo $labels[$tx['type']] ?? $tx['type'];
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($tx['description'] ?? '-'); ?></td>
                                        <td class="text-end"><?php echo formatRupiah($tx['amount']); ?></td>
                                        <td class="text-end"><?php echo formatRupiah($tx['balance_after'] ?? 0); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
