<?php
/**
 * Admin - Transaksi
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

require_once 'includes/init.php';

$auth->requireAuth();
$auth->requireRole(['admin', 'super_admin']);

$user = $auth->getCurrentUser();

$typeFilter = $_GET['type'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$conditions = [];
$params = [];
if ($typeFilter !== 'all') {
    $conditions[] = 't.type = ?';
    $params[] = $typeFilter;
}
if ($search !== '') {
    $conditions[] = '(u.full_name LIKE ? OR u.nik LIKE ? OR t.reference_number LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
$whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$totalCount = (int)($db->selectOne("SELECT COUNT(*) as total FROM transactions t JOIN users u ON t.user_id = u.id $whereClause", $params)['total'] ?? 0);
$totalPages = max(1, ceil($totalCount / $limit));

$transactions = $db->select(
    "SELECT t.*, u.full_name, u.nik
     FROM transactions t
     JOIN users u ON t.user_id = u.id
     $whereClause
     ORDER BY t.created_at DESC
     LIMIT $limit OFFSET $offset",
    $params
);

$stats = $db->selectOne(
    "SELECT
        SUM(CASE WHEN type = 'savings_deposit' AND status = 'completed' THEN amount ELSE 0 END) as total_deposit,
        SUM(CASE WHEN type = 'savings_withdrawal' AND status = 'completed' THEN amount ELSE 0 END) as total_withdrawal,
        SUM(CASE WHEN type = 'loan_disbursement' AND status = 'completed' THEN amount ELSE 0 END) as total_disbursement,
        SUM(CASE WHEN type = 'loan_payment' AND status = 'completed' THEN amount ELSE 0 END) as total_payment
     FROM transactions"
);

$typeLabels = [
    'savings_deposit' => 'Setoran Tabungan',
    'savings_withdrawal' => 'Penarikan Tabungan',
    'loan_disbursement' => 'Pencairan Pinjaman',
    'loan_payment' => 'Pembayaran Pinjaman',
    'interest_credit' => 'Bunga Tabungan',
    'fee_debit' => 'Biaya/Denda'
];

renderAdminHeader('Transaksi - Admin', 'admin-transactions.php', $user, 'Transaksi');
?>

<div class="row mb-4">
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-success p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Setoran</h6><h5 class="mb-0"><?php echo formatRupiah($stats['total_deposit'] ?? 0); ?></h5><i class="fas fa-arrow-down card-icon"></i></div></div></div>
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-danger p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Penarikan</h6><h5 class="mb-0"><?php echo formatRupiah($stats['total_withdrawal'] ?? 0); ?></h5><i class="fas fa-arrow-up card-icon"></i></div></div></div>
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-primary p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Pencairan Pinjaman</h6><h5 class="mb-0"><?php echo formatRupiah($stats['total_disbursement'] ?? 0); ?></h5><i class="fas fa-hand-holding-usd card-icon"></i></div></div></div>
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-info p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Pembayaran Pinjaman</h6><h5 class="mb-0"><?php echo formatRupiah($stats['total_payment'] ?? 0); ?></h5><i class="fas fa-money-check card-icon"></i></div></div></div>
</div>

<div class="card content-card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <select name="type" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>Semua Jenis</option>
                    <?php foreach ($typeLabels as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $typeFilter === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <input type="text" name="search" class="form-control" placeholder="Cari nama, NIK, no. referensi..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Cari</button>
            </div>
        </form>
    </div>
</div>

<div class="card content-card">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-exchange-alt text-primary me-2"></i> Riwayat Transaksi (<?php echo $totalCount; ?>)</h5></div>
    <div class="card-body p-0">
        <?php if (empty($transactions)): ?>
            <div class="text-center text-muted py-5"><i class="fas fa-inbox fa-3x mb-3"></i><p>Tidak ada transaksi.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>No. Referensi</th><th>Nasabah</th><th>Jenis</th><th>Jumlah</th><th>Tanggal</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $t): ?>
                            <?php $isCredit = in_array($t['type'], ['savings_deposit', 'loan_disbursement', 'interest_credit']); ?>
                            <tr>
                                <td><small><strong><?php echo htmlspecialchars($t['reference_number']); ?></strong></small></td>
                                <td><?php echo htmlspecialchars($t['full_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($t['nik']); ?></small></td>
                                <td><span class="badge bg-light text-dark"><?php echo $typeLabels[$t['type']] ?? $t['type']; ?></span></td>
                                <td class="<?php echo $isCredit ? 'text-success' : 'text-danger'; ?> fw-bold"><?php echo ($isCredit ? '+' : '-') . ' ' . formatRupiah($t['amount']); ?></td>
                                <td><?php echo formatDateIndo($t['transaction_date']); ?></td>
                                <td><span class="badge bg-<?php echo $t['status'] === 'completed' ? 'success' : ($t['status'] === 'pending' ? 'warning' : 'secondary'); ?>"><?php echo ucfirst($t['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-center mt-4"><?php echo generatePagination($page, $totalPages, 'admin-transactions.php', ['type' => $typeFilter, 'search' => $search]); ?></div>
<?php endif; ?>

<?php renderAdminFooter(); ?>
