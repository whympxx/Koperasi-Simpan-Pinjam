<?php
/**
 * Admin - Akun Tabungan
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

require_once 'includes/init.php';

$auth->requireAuth();
$auth->requireRole(['admin', 'super_admin']);

$user = $auth->getCurrentUser();

$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$conditions = [];
$params = [];
if ($statusFilter !== 'all') {
    $conditions[] = 'sa.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $conditions[] = '(u.full_name LIKE ? OR u.nik LIKE ? OR sa.account_number LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
$whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$totalCount = (int)($db->selectOne("SELECT COUNT(*) as total FROM savings_accounts sa JOIN users u ON sa.user_id = u.id $whereClause", $params)['total'] ?? 0);
$totalPages = max(1, ceil($totalCount / $limit));

$accounts = $db->select(
    "SELECT sa.*, u.full_name, u.nik
     FROM savings_accounts sa
     JOIN users u ON sa.user_id = u.id
     $whereClause
     ORDER BY sa.balance DESC
     LIMIT $limit OFFSET $offset",
    $params
);

$stats = $db->selectOne(
    "SELECT
        COUNT(*) as total_accounts,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_accounts,
        SUM(CASE WHEN status = 'active' THEN balance ELSE 0 END) as total_balance
     FROM savings_accounts"
);

renderAdminHeader('Akun Tabungan - Admin', 'admin-savings.php', $user, 'Akun Tabungan');
?>

<div class="row mb-4">
    <div class="col-md-4 mb-3"><div class="stats-card stats-gradient-info p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Total Akun</h6><h3 class="mb-0"><?php echo (int)$stats['total_accounts']; ?></h3><i class="fas fa-piggy-bank card-icon"></i></div></div></div>
    <div class="col-md-4 mb-3"><div class="stats-card stats-gradient-success p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Akun Aktif</h6><h3 class="mb-0"><?php echo (int)$stats['active_accounts']; ?></h3><i class="fas fa-check-circle card-icon"></i></div></div></div>
    <div class="col-md-4 mb-3"><div class="stats-card stats-gradient-primary p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Total Saldo</h6><h4 class="mb-0"><?php echo formatRupiah($stats['total_balance'] ?? 0); ?></h4><i class="fas fa-wallet card-icon"></i></div></div></div>
</div>

<div class="card content-card mb-4">
    <div class="card-body">
        <div class="row align-items-center g-2">
            <div class="col-md-7">
                <div class="d-flex gap-2 flex-wrap">
                    <?php $tabs = ['all' => 'Semua', 'active' => 'Aktif', 'inactive' => 'Nonaktif', 'closed' => 'Ditutup'];
                    foreach ($tabs as $key => $label): ?>
                        <a href="?status=<?php echo $key; ?>" class="btn btn-sm <?php echo $statusFilter === $key ? 'btn-primary' : 'btn-outline-primary'; ?>"><?php echo $label; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-5">
                <form method="GET" class="d-flex">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama, NIK, no. rekening..." value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-outline-secondary ms-2" type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card content-card">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-piggy-bank text-primary me-2"></i> Daftar Akun Tabungan (<?php echo $totalCount; ?>)</h5></div>
    <div class="card-body p-0">
        <?php if (empty($accounts)): ?>
            <div class="text-center text-muted py-5"><i class="fas fa-inbox fa-3x mb-3"></i><p>Tidak ada data.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>No. Rekening</th><th>Nasabah</th><th>Saldo</th><th>Bunga</th><th>Dibuka</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($accounts as $acc): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($acc['account_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($acc['full_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($acc['nik']); ?></small></td>
                                <td><strong class="text-success"><?php echo formatRupiah($acc['balance']); ?></strong></td>
                                <td><?php echo $acc['interest_rate']; ?>%</td>
                                <td><?php echo formatDateIndo($acc['opened_date']); ?></td>
                                <td><span class="badge bg-<?php echo $acc['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($acc['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-center mt-4"><?php echo generatePagination($page, $totalPages, 'admin-savings.php', ['status' => $statusFilter, 'search' => $search]); ?></div>
<?php endif; ?>

<?php renderAdminFooter(); ?>
