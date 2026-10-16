<?php
/**
 * Admin - Pengajuan Pinjaman
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

require_once 'includes/init.php';

$auth->requireAuth();
$auth->requireRole(['admin', 'super_admin']);

$user = $auth->getCurrentUser();
$loanManager = new LoanManager();

$error = '';
$success = '';

// Handle approve / reject
if ($_POST) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    $applicationId = (int)($_POST['application_id'] ?? 0);
    $notes = SecurityHelper::sanitize($_POST['notes'] ?? '');

    if (!$auth->verifyCSRFToken($csrfToken)) {
        $error = 'Token keamanan tidak valid.';
    } elseif (!in_array($action, ['approve', 'reject']) || $applicationId <= 0) {
        $error = 'Data tidak valid.';
    } else {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $result = $loanManager->reviewApplication($applicationId, $user['id'], $status, $notes);
        $result['success'] ? $success = $result['message'] : $error = $result['message'];
    }
}

// Filters
$status = $_GET['status'] ?? 'pending';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

$conditions = [];
$params = [];
if ($status !== 'all') {
    $conditions[] = 'la.status = ?';
    $params[] = $status;
}
if ($search !== '') {
    $conditions[] = '(u.full_name LIKE ? OR u.nik LIKE ? OR la.application_number LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}
$whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$totalCount = (int)($db->selectOne("SELECT COUNT(*) as total FROM loan_applications la JOIN users u ON la.user_id = u.id $whereClause", $params)['total'] ?? 0);
$totalPages = max(1, ceil($totalCount / $limit));

$applications = $db->select(
    "SELECT la.*, u.full_name, u.nik, u.phone, u.monthly_salary, d.name as division_name
     FROM loan_applications la
     JOIN users u ON la.user_id = u.id
     LEFT JOIN divisions d ON u.division_id = d.id
     $whereClause
     ORDER BY la.created_at DESC
     LIMIT $limit OFFSET $offset",
    $params
);

$stats = $db->selectOne(
    "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
     FROM loan_applications"
);

renderAdminHeader('Pengajuan Pinjaman - Admin', 'admin-loans.php', $user, 'Pengajuan Pinjaman');
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-primary p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Total Pengajuan</h6><h3 class="mb-0"><?php echo (int)$stats['total']; ?></h3><i class="fas fa-list card-icon"></i></div></div></div>
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-warning p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Menunggu</h6><h3 class="mb-0"><?php echo (int)$stats['pending']; ?></h3><i class="fas fa-clock card-icon"></i></div></div></div>
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-success p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Disetujui</h6><h3 class="mb-0"><?php echo (int)$stats['approved']; ?></h3><i class="fas fa-check card-icon"></i></div></div></div>
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-danger p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Ditolak</h6><h3 class="mb-0"><?php echo (int)$stats['rejected']; ?></h3><i class="fas fa-times card-icon"></i></div></div></div>
</div>

<div class="card content-card mb-4">
    <div class="card-body">
        <div class="row align-items-center g-2">
            <div class="col-md-7">
                <div class="d-flex gap-2 flex-wrap">
                    <?php
                    $tabs = ['all' => 'Semua', 'pending' => 'Pending', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];
                    foreach ($tabs as $key => $label):
                        $active = $status === $key;
                    ?>
                        <a href="?status=<?php echo $key; ?>" class="btn btn-sm <?php echo $active ? 'btn-primary' : 'btn-outline-primary'; ?>"><?php echo $label; ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-5">
                <form method="GET" class="d-flex">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama, NIK, no. aplikasi..." value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-outline-secondary ms-2" type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <?php if (empty($applications)): ?>
        <div class="col-12"><div class="text-center text-muted py-5"><i class="fas fa-folder-open fa-3x mb-3"></i><p>Tidak ada pengajuan.</p></div></div>
    <?php else: ?>
        <?php foreach ($applications as $app): ?>
            <div class="col-lg-6 mb-4">
                <div class="card content-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="mb-0"><?php echo htmlspecialchars($app['application_number']); ?></h6>
                                <small class="text-muted"><?php echo formatDateIndo($app['created_at'], true); ?></small>
                            </div>
                            <?php echo getStatusBadge($app['status'], 'loan'); ?>
                        </div>
                        <div class="row mb-2">
                            <div class="col-7">
                                <strong><?php echo htmlspecialchars($app['full_name']); ?></strong><br>
                                <small class="text-muted">NIK: <?php echo htmlspecialchars($app['nik']); ?> &middot; <?php echo htmlspecialchars($app['division_name'] ?? '-'); ?></small>
                            </div>
                            <div class="col-5 text-end">
                                <h5 class="mb-0 text-primary"><?php echo formatRupiah($app['loan_amount']); ?></h5>
                                <small class="text-muted"><?php echo (int)$app['loan_term_months']; ?> bulan</small>
                            </div>
                        </div>
                        <div class="row text-center small bg-light rounded py-2 mb-3">
                            <div class="col-4"><span class="text-muted d-block">Gaji</span><strong><?php echo formatRupiah($app['monthly_salary']); ?></strong></div>
                            <div class="col-4"><span class="text-muted d-block">Angsuran</span><strong><?php echo formatRupiah($app['monthly_installment']); ?></strong></div>
                            <div class="col-4"><span class="text-muted d-block">Total</span><strong><?php echo formatRupiah($app['total_payment']); ?></strong></div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="admin-loan-detail.php?id=<?php echo $app['id']; ?>" class="btn btn-outline-info btn-sm flex-fill"><i class="fas fa-eye me-1"></i> Detail</a>
                            <?php if ($app['status'] === 'pending'): ?>
                                <button class="btn btn-success btn-sm flex-fill" onclick="openModal(<?php echo $app['id']; ?>, 'approve', '<?php echo htmlspecialchars(addslashes($app['full_name'])); ?>')"><i class="fas fa-check me-1"></i> Setujui</button>
                                <button class="btn btn-danger btn-sm flex-fill" onclick="openModal(<?php echo $app['id']; ?>, 'reject', '<?php echo htmlspecialchars(addslashes($app['full_name'])); ?>')"><i class="fas fa-times me-1"></i> Tolak</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-center"><?php echo generatePagination($page, $totalPages, 'admin-loans.php', ['status' => $status, 'search' => $search]); ?></div>
<?php endif; ?>

<!-- Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                    <input type="hidden" name="action" id="modalAction">
                    <input type="hidden" name="application_id" id="modalAppId">
                    <label class="form-label">Catatan:</label>
                    <textarea name="notes" class="form-control" rows="4" placeholder="Catatan keputusan..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn" id="modalSubmit">Proses</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openModal(id, action, name) {
        document.getElementById('modalAppId').value = id;
        document.getElementById('modalAction').value = action;
        const title = document.getElementById('modalTitle');
        const submit = document.getElementById('modalSubmit');
        if (action === 'approve') {
            title.textContent = 'Setujui Pengajuan - ' + name;
            submit.textContent = 'Setujui';
            submit.className = 'btn btn-success';
        } else {
            title.textContent = 'Tolak Pengajuan - ' + name;
            submit.textContent = 'Tolak';
            submit.className = 'btn btn-danger';
        }
        new bootstrap.Modal(document.getElementById('reviewModal')).show();
    }
</script>

<?php renderAdminFooter(); ?>
