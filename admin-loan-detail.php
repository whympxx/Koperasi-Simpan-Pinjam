<?php
/**
 * Admin - Detail Pengajuan Pinjaman
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

require_once 'includes/init.php';

$auth->requireAuth();
$auth->requireRole(['admin', 'super_admin']);

$user = $auth->getCurrentUser();
$loanManager = new LoanManager();

$applicationId = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if ($_POST) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    $notes = SecurityHelper::sanitize($_POST['notes'] ?? '');

    if (!$auth->verifyCSRFToken($csrfToken)) {
        $error = 'Token keamanan tidak valid.';
    } elseif (in_array($action, ['approve', 'reject'])) {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $result = $loanManager->reviewApplication($applicationId, $user['id'], $status, $notes);
        $result['success'] ? $success = $result['message'] : $error = $result['message'];
    }
}

$app = $db->selectOne(
    "SELECT la.*, u.full_name, u.nik, u.phone, u.email, u.position, u.employment_date, u.monthly_salary, u.address,
            d.name as division_name, ru.full_name as reviewed_by_name, au.full_name as approved_by_name
     FROM loan_applications la
     JOIN users u ON la.user_id = u.id
     LEFT JOIN divisions d ON u.division_id = d.id
     LEFT JOIN users ru ON la.reviewed_by = ru.id
     LEFT JOIN users au ON la.approved_by = au.id
     WHERE la.id = ?",
    [$applicationId]
);

renderAdminHeader('Detail Pengajuan - Admin', 'admin-loans.php', $user, 'Detail Pengajuan Pinjaman');

if (!$app) {
    echo '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Pengajuan tidak ditemukan. <a href="admin-loans.php">Kembali</a></div>';
    renderAdminFooter();
    exit;
}
?>

<a href="admin-loans.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="fas fa-arrow-left me-1"></i> Kembali ke daftar</a>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card content-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-file-invoice-dollar text-primary me-2"></i> <?php echo htmlspecialchars($app['application_number']); ?></h5>
                <?php echo getStatusBadge($app['status'], 'loan'); ?>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><small class="text-muted d-block">Jumlah Pinjaman</small><h5 class="text-primary"><?php echo formatRupiah($app['loan_amount']); ?></h5></div>
                    <div class="col-md-4"><small class="text-muted d-block">Tenor</small><h5><?php echo (int)$app['loan_term_months']; ?> bulan</h5></div>
                    <div class="col-md-4"><small class="text-muted d-block">Bunga</small><h5><?php echo $app['interest_rate']; ?>% / bulan</h5></div>
                    <div class="col-md-4"><small class="text-muted d-block">Angsuran / Bulan</small><strong><?php echo formatRupiah($app['monthly_installment']); ?></strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Total Pembayaran</small><strong><?php echo formatRupiah($app['total_payment']); ?></strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">Tanggal Pengajuan</small><strong><?php echo formatDateIndo($app['created_at'], true); ?></strong></div>
                    <div class="col-md-12"><small class="text-muted d-block">Tujuan</small><strong><?php echo htmlspecialchars($app['purpose']); ?></strong></div>
                    <?php if (!empty($app['description'])): ?>
                        <div class="col-md-12"><small class="text-muted d-block">Keterangan</small><p class="mb-0"><?php echo nl2br(htmlspecialchars($app['description'])); ?></p></div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($app['review_notes']) || !empty($app['rejected_reason'])): ?>
                    <hr>
                    <?php if (!empty($app['review_notes'])): ?>
                        <p class="mb-1"><strong>Catatan Review:</strong> <?php echo htmlspecialchars($app['review_notes']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($app['rejected_reason'])): ?>
                        <p class="mb-1 text-danger"><strong>Alasan Ditolak:</strong> <?php echo htmlspecialchars($app['rejected_reason']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($app['approved_by_name'])): ?>
                        <small class="text-muted">Disetujui oleh: <?php echo htmlspecialchars($app['approved_by_name']); ?> &middot; <?php echo formatDateIndo($app['approved_at'], true); ?></small>
                    <?php elseif (!empty($app['reviewed_by_name'])): ?>
                        <small class="text-muted">Direview oleh: <?php echo htmlspecialchars($app['reviewed_by_name']); ?> &middot; <?php echo formatDateIndo($app['reviewed_at'], true); ?></small>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($app['status'] === 'pending'): ?>
            <div class="card content-card">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-gavel me-2"></i> Keputusan</h6></div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                        <input type="hidden" name="action" id="decisionAction">
                        <div class="mb-3">
                            <label class="form-label">Catatan</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Catatan keputusan..."></textarea>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success" onclick="document.getElementById('decisionAction').value='approve'"><i class="fas fa-check me-1"></i> Setujui</button>
                            <button type="submit" class="btn btn-danger" onclick="document.getElementById('decisionAction').value='reject'"><i class="fas fa-times me-1"></i> Tolak</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4 mb-4">
        <div class="card content-card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-user me-2"></i> Data Pemohon</h6></div>
            <div class="card-body">
                <p class="mb-1"><strong><?php echo htmlspecialchars($app['full_name']); ?></strong></p>
                <p class="mb-1 small text-muted">NIK: <?php echo htmlspecialchars($app['nik']); ?></p>
                <hr>
                <p class="mb-1 small"><i class="fas fa-building me-2 text-muted"></i><?php echo htmlspecialchars($app['division_name'] ?? '-'); ?></p>
                <p class="mb-1 small"><i class="fas fa-id-badge me-2 text-muted"></i><?php echo htmlspecialchars($app['position'] ?? '-'); ?></p>
                <p class="mb-1 small"><i class="fas fa-phone me-2 text-muted"></i><?php echo htmlspecialchars($app['phone']); ?></p>
                <p class="mb-1 small"><i class="fas fa-envelope me-2 text-muted"></i><?php echo htmlspecialchars($app['email']); ?></p>
                <p class="mb-1 small"><i class="fas fa-money-bill me-2 text-muted"></i>Gaji: <?php echo formatRupiah($app['monthly_salary']); ?></p>
                <p class="mb-1 small"><i class="fas fa-calendar me-2 text-muted"></i>Mulai kerja: <?php echo $app['employment_date'] ? formatDateIndo($app['employment_date']) : '-'; ?></p>
                <?php if (!empty($app['address'])): ?>
                    <p class="mb-0 small"><i class="fas fa-map-marker-alt me-2 text-muted"></i><?php echo htmlspecialchars($app['address']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php renderAdminFooter(); ?>
