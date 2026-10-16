<?php
/**
 * Admin - Pinjaman Aktif
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

require_once 'includes/init.php';

$auth->requireAuth();
$auth->requireRole(['admin', 'super_admin']);

$user = $auth->getCurrentUser();

$statusFilter = $_GET['status'] ?? 'active';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$conditions = [];
$params = [];
if ($statusFilter !== 'all') {
    $conditions[] = 'al.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $conditions[] = '(u.full_name LIKE ? OR u.nik LIKE ? OR al.loan_number LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
$whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$totalCount = (int)($db->selectOne("SELECT COUNT(*) as total FROM active_loans al JOIN users u ON al.user_id = u.id $whereClause", $params)['total'] ?? 0);
$totalPages = max(1, ceil($totalCount / $limit));

$loans = $db->select(
    "SELECT al.*, u.full_name, u.nik
     FROM active_loans al
     JOIN users u ON al.user_id = u.id
     $whereClause
     ORDER BY al.created_at DESC
     LIMIT $limit OFFSET $offset",
    $params
);

$stats = $db->selectOne(
    "SELECT
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue,
        SUM(CASE WHEN status = 'active' THEN remaining_balance ELSE 0 END) as outstanding
     FROM active_loans"
);

renderAdminHeader('Pinjaman Aktif - Admin', 'admin-active-loans.php', $user, 'Pinjaman Aktif');
?>

<div class="row mb-4">
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-success p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Pinjaman Aktif</h6><h3 class="mb-0"><?php echo (int)$stats['active']; ?></h3><i class="fas fa-hand-holding-usd card-icon"></i></div></div></div>
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-info p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Lunas</h6><h3 class="mb-0"><?php echo (int)$stats['completed']; ?></h3><i class="fas fa-check-double card-icon"></i></div></div></div>
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-danger p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Menunggak</h6><h3 class="mb-0"><?php echo (int)$stats['overdue']; ?></h3><i class="fas fa-exclamation-triangle card-icon"></i></div></div></div>
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-warning p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Total Outstanding</h6><h4 class="mb-0"><?php echo formatRupiah($stats['outstanding'] ?? 0); ?></h4><i class="fas fa-money-bill-wave card-icon"></i></div></div></div>
</div>

<div class="card content-card mb-4">
    <div class="card-body">
        <div class="row align-items-center g-2">
            <div class="col-md-7">
                <div class="d-flex gap-2 flex-wrap">
                    <?php $tabs = ['all' => 'Semua', 'active' => 'Aktif', 'overdue' => 'Menunggak', 'completed' => 'Lunas'];
                    foreach ($tabs as $key => $label): ?>
                        <a href="?status=<?php echo $key; ?>" class="btn btn-sm <?php echo $statusFilter === $key ? 'btn-primary' : 'btn-outline-primary'; ?>"><?php echo $label; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-5">
                <form method="GET" class="d-flex">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama, NIK, no. pinjaman..." value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-outline-secondary ms-2" type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card content-card">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-clipboard-list text-primary me-2"></i> Daftar Pinjaman Aktif (<?php echo $totalCount; ?>)</h5></div>
    <div class="card-body p-0">
        <?php if (empty($loans)): ?>
            <div class="text-center text-muted py-5"><i class="fas fa-inbox fa-3x mb-3"></i><p>Tidak ada data.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>No. Pinjaman</th><th>Nasabah</th><th>Pokok</th><th>Sisa</th><th>Angsuran</th><th>Sisa Tenor</th><th>Jatuh Tempo</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loans as $loan): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($loan['loan_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($loan['full_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($loan['nik']); ?></small></td>
                                <td><?php echo formatRupiah($loan['original_amount']); ?></td>
                                <td><strong class="text-primary"><?php echo formatRupiah($loan['remaining_balance']); ?></strong></td>
                                <td><?php echo formatRupiah($loan['monthly_installment']); ?></td>
                                <td><?php echo (int)$loan['remaining_months']; ?> bln</td>
                                <td class="<?php echo isOverdue($loan['next_due_date']) ? 'text-danger fw-bold' : ''; ?>"><?php echo formatDateIndo($loan['next_due_date']); ?></td>
                                <td><span class="badge bg-<?php echo $loan['status'] === 'active' ? 'success' : ($loan['status'] === 'overdue' ? 'danger' : 'secondary'); ?>"><?php echo ucfirst($loan['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-center mt-4"><?php echo generatePagination($page, $totalPages, 'admin-active-loans.php', ['status' => $statusFilter, 'search' => $search]); ?></div>
<?php endif; ?>

<?php renderAdminFooter(); ?>
