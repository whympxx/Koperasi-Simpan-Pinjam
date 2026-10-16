<?php
require_once '../includes/init.php';

// Require authentication and admin role
$auth->requireAuth();
$auth->requireRole(['admin', 'super_admin']);

$user = $auth->getCurrentUser();
$loanManager = new LoanManager();

$error = '';
$success = '';

// Handle approval/rejection and settings update
if ($_POST) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if (!$auth->verifyCSRFToken($csrfToken)) {
        $error = 'Token keamanan tidak valid.';
    } elseif ($action === 'save_auto_approve') {
        // Save auto-approval configuration
        $enabled = !empty($_POST['auto_approve_enabled']);
        $maxAmount = (float)str_replace(['.', ','], ['', '.'], $_POST['auto_approve_max_amount'] ?? '0');
        $maxTerm = (int)($_POST['auto_approve_max_term'] ?? 0);
        
        if ($maxAmount <= 0) {
            $error = 'Batas maksimal jumlah pinjaman harus lebih dari 0.';
        } elseif ($maxTerm < 1 || $maxTerm > 18) {
            $error = 'Batas maksimal tenor harus antara 1-18 bulan.';
        } else {
            $dbHelper->setSystemSetting('loan_auto_approve_enabled', $enabled, 'boolean', 'Aktifkan persetujuan pinjaman otomatis', $user['id']);
            $dbHelper->setSystemSetting('loan_auto_approve_max_amount', $maxAmount, 'number', 'Batas maksimal jumlah pinjaman untuk auto-approve', $user['id']);
            $dbHelper->setSystemSetting('loan_auto_approve_max_term_months', $maxTerm, 'number', 'Batas maksimal tenor (bulan) untuk auto-approve', $user['id']);
            
            $dbHelper->logActivity($user['id'], 'auto_approve_settings_updated', 'system_setting', null, null, [
                'enabled' => $enabled,
                'max_amount' => $maxAmount,
                'max_term' => $maxTerm
            ]);
            
            $success = 'Pengaturan auto-approve berhasil disimpan.';
        }
    } else {
        $applicationId = (int)($_POST['application_id'] ?? 0);
        $notes = SecurityHelper::sanitize($_POST['notes'] ?? '');
        
        if (!in_array($action, ['approve', 'reject']) || empty($applicationId)) {
            $error = 'Data tidak valid.';
        } else {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $result = $loanManager->reviewApplication($applicationId, $user['id'], $status, $notes);
            
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Load current auto-approve settings (with config fallbacks)
$autoApproveEnabled = $dbHelper->getSystemSetting('loan_auto_approve_enabled');
if ($autoApproveEnabled === null) {
    $autoApproveEnabled = LOAN_AUTO_APPROVE_ENABLED;
}
$autoApproveMaxAmount = $dbHelper->getSystemSetting('loan_auto_approve_max_amount');
if ($autoApproveMaxAmount === null || $autoApproveMaxAmount <= 0) {
    $autoApproveMaxAmount = LOAN_AUTO_APPROVE_MAX_AMOUNT;
}
$autoApproveMaxTerm = $dbHelper->getSystemSetting('loan_auto_approve_max_term_months');
if ($autoApproveMaxTerm === null || $autoApproveMaxTerm <= 0) {
    $autoApproveMaxTerm = LOAN_AUTO_APPROVE_MAX_TERM_MONTHS;
}

// Pagination and filters
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;
$status = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';

// Build where clause
$whereConditions = [];
$params = [];

if ($status !== 'all') {
    $whereConditions[] = 'la.status = ?';
    $params[] = $status;
}

if ($search) {
    $whereConditions[] = '(u.full_name LIKE ? OR u.nik LIKE ? OR la.application_number LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get loan applications
$applications = $db->select(
    "SELECT la.*, u.full_name, u.nik, u.phone, u.email, u.monthly_salary,
            d.name as division_name, ru.full_name as reviewed_by_name,
            au.full_name as approved_by_name
     FROM loan_applications la
     JOIN users u ON la.user_id = u.id
     LEFT JOIN divisions d ON u.division_id = d.id
     LEFT JOIN users ru ON la.reviewed_by = ru.id
     LEFT JOIN users au ON la.approved_by = au.id
     $whereClause
     ORDER BY la.created_at DESC
     LIMIT $limit OFFSET $offset",
    $params
);

// Get total count for pagination
$totalCount = $db->selectOne(
    "SELECT COUNT(*) as total FROM loan_applications la
     JOIN users u ON la.user_id = u.id
     $whereClause",
    $params
)['total'];

$totalPages = ceil($totalCount / $limit);

// Get statistics
$stats = $db->selectOne(
    "SELECT 
        COUNT(*) as total_applications,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'reviewing' THEN 1 ELSE 0 END) as reviewing_count,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        SUM(CASE WHEN status = 'approved' THEN loan_amount ELSE 0 END) as approved_amount
     FROM loan_applications"
);

$pageTitle = 'Persetujuan Pinjaman - Admin Dashboard';
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
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .admin-sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            z-index: 1000;
        }
        
        .admin-sidebar .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .admin-sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .admin-sidebar .nav-link:hover,
        .admin-sidebar .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
            border-left-color: #fff;
        }
        
        .admin-main-content {
            margin-left: 250px;
            padding: 2rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .application-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border-left: 4px solid #e9ecef;
        }
        
        .application-card.pending { border-left-color: #ffc107; }
        .application-card.approved { border-left-color: #28a745; }
        .application-card.rejected { border-left-color: #dc3545; }
        .application-card.reviewing { border-left-color: #17a2b8; }
    </style>
</head>
<body>
    <!-- Admin Sidebar -->
    <nav class="admin-sidebar">
        <div class="sidebar-header text-center">
            <h5 class="text-white mb-0">
                <i class="fas fa-user-shield me-2"></i>
                Admin Panel
            </h5>
        </div>
        
        <ul class="nav flex-column sidebar-nav">
            <li class="nav-item">
                <a class="nav-link" href="../admin-dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="loan-approvals.php">
                    <i class="fas fa-hand-holding-usd me-2"></i> Persetujuan Pinjaman
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="withdrawal-approvals.php">
                    <i class="fas fa-money-bill-wave me-2"></i> Persetujuan Penarikan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="user-management.php">
                    <i class="fas fa-users me-2"></i> Manajemen User
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i> Laporan
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../dashboard.php">
                    <i class="fas fa-arrow-left me-2"></i> Kembali ke User
                </a>
            </li>
            <li class="nav-item mt-3">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="admin-main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Persetujuan Pinjaman</h2>
                <p class="text-muted">Kelola pengajuan pinjaman dari nasabah</p>
            </div>
            <div class="text-end">
                <span class="text-muted">Admin: <strong><?php echo $user['name']; ?></strong></span>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stats-card p-3 text-center">
                    <i class="fas fa-list fa-2x text-primary mb-2"></i>
                    <h4 class="mb-0"><?php echo $stats['total_applications']; ?></h4>
                    <small class="text-muted">Total Aplikasi</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card p-3 text-center">
                    <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                    <h4 class="mb-0"><?php echo $stats['pending_count']; ?></h4>
                    <small class="text-muted">Pending</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card p-3 text-center">
                    <i class="fas fa-eye fa-2x text-info mb-2"></i>
                    <h4 class="mb-0"><?php echo $stats['reviewing_count']; ?></h4>
                    <small class="text-muted">Review</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card p-3 text-center">
                    <i class="fas fa-check fa-2x text-success mb-2"></i>
                    <h4 class="mb-0"><?php echo $stats['approved_count']; ?></h4>
                    <small class="text-muted">Disetujui</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card p-3 text-center">
                    <i class="fas fa-times fa-2x text-danger mb-2"></i>
                    <h4 class="mb-0"><?php echo $stats['rejected_count']; ?></h4>
                    <small class="text-muted">Ditolak</small>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-card p-3 text-center">
                    <i class="fas fa-money-bill fa-2x text-success mb-2"></i>
                    <h4 class="mb-0"><?php echo formatRupiah($stats['approved_amount'], false); ?></h4>
                    <small class="text-muted">Total Disetujui</small>
                </div>
            </div>
        </div>

        <!-- Auto-Approve Settings -->
        <div class="card mb-4 border-0" style="box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div class="card-header bg-white d-flex justify-content-between align-items-center" 
                 style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#autoApproveSettings">
                <h5 class="mb-0">
                    <i class="fas fa-robot text-primary me-2"></i>
                    Pengaturan Persetujuan Otomatis (Auto-Approve)
                    <?php if ($autoApproveEnabled): ?>
                        <span class="badge bg-success ms-2">Aktif</span>
                    <?php else: ?>
                        <span class="badge bg-secondary ms-2">Nonaktif</span>
                    <?php endif; ?>
                </h5>
                <i class="fas fa-chevron-down text-muted"></i>
            </div>
            <div class="collapse" id="autoApproveSettings">
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        <i class="fas fa-info-circle me-1"></i>
                        Pengajuan pinjaman yang memenuhi kriteria di bawah ini (dan lolos pengecekan kelayakan dasar) akan 
                        <strong>disetujui otomatis</strong> oleh sistem tanpa perlu review manual.
                    </p>
                    <form method="POST" class="row g-3 align-items-end">
                        <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="save_auto_approve">
                        
                        <div class="col-md-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" 
                                       id="auto_approve_enabled" name="auto_approve_enabled" value="1"
                                       <?php echo $autoApproveEnabled ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="auto_approve_enabled">
                                    Aktifkan Auto-Approve
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Batas Maksimal Jumlah Pinjaman</label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="text" class="form-control" name="auto_approve_max_amount"
                                       value="<?php echo number_format($autoApproveMaxAmount, 0, ',', '.'); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Batas Maksimal Tenor</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="auto_approve_max_term"
                                       min="1" max="24" value="<?php echo (int)$autoApproveMaxTerm; ?>">
                                <span class="input-group-text">Bulan</span>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save me-1"></i> Simpan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <div class="d-flex gap-2">
                            <a href="?status=all" class="btn btn-sm <?php echo $status === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                Semua
                            </a>
                            <a href="?status=pending" class="btn btn-sm <?php echo $status === 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                                Pending
                            </a>
                            <a href="?status=reviewing" class="btn btn-sm <?php echo $status === 'reviewing' ? 'btn-info' : 'btn-outline-info'; ?>">
                                Review
                            </a>
                            <a href="?status=approved" class="btn btn-sm <?php echo $status === 'approved' ? 'btn-success' : 'btn-outline-success'; ?>">
                                Disetujui
                            </a>
                            <a href="?status=rejected" class="btn btn-sm <?php echo $status === 'rejected' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                                Ditolak
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 offset-md-4">
                        <form method="GET" class="d-flex">
                            <input type="hidden" name="status" value="<?php echo $status; ?>">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Cari nama, NIK, atau nomor aplikasi..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applications List -->
        <div class="row">
            <?php if (empty($applications)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                        <h5>Tidak Ada Aplikasi</h5>
                        <p class="text-muted">Belum ada aplikasi pinjaman yang tersedia.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($applications as $app): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="application-card <?php echo $app['status']; ?> p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="mb-1"><?php echo $app['application_number']; ?></h6>
                                    <p class="mb-0 text-muted"><?php echo formatDateIndo($app['created_at'], true); ?></p>
                                </div>
                                <div>
                                    <?php echo getStatusBadge($app['status'], 'loan'); ?>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-8">
                                    <strong><?php echo $app['full_name']; ?></strong><br>
                                    <small class="text-muted">
                                        NIK: <?php echo $app['nik']; ?><br>
                                        <?php echo $app['division_name']; ?><br>
                                        <?php echo $app['phone']; ?>
                                    </small>
                                </div>
                                <div class="col-4 text-end">
                                    <h5 class="mb-0 text-primary"><?php echo formatRupiah($app['loan_amount']); ?></h5>
                                    <small class="text-muted"><?php echo $app['loan_term_months']; ?> bulan</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">Tujuan:</small><br>
                                <span><?php echo $app['purpose']; ?></span>
                            </div>
                            
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <small class="text-muted">Gaji</small><br>
                                    <strong><?php echo formatRupiah($app['monthly_salary']); ?></strong>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">Angsuran</small><br>
                                    <strong><?php echo formatRupiah($app['monthly_installment']); ?></strong>
                                </div>
                                <div class="col-4">
                                    <small class="text-muted">Total</small><br>
                                    <strong><?php echo formatRupiah($app['total_payment']); ?></strong>
                                </div>
                            </div>
                            
                            <?php if ($app['status'] === 'pending'): ?>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-success btn-sm flex-fill" 
                                            onclick="showApprovalModal(<?php echo $app['id']; ?>, 'approve', '<?php echo addslashes($app['full_name']); ?>')">
                                        <i class="fas fa-check me-1"></i> Setujui
                                    </button>
                                    <button class="btn btn-danger btn-sm flex-fill" 
                                            onclick="showApprovalModal(<?php echo $app['id']; ?>, 'reject', '<?php echo addslashes($app['full_name']); ?>')">
                                        <i class="fas fa-times me-1"></i> Tolak
                                    </button>
                                    <button class="btn btn-info btn-sm" 
                                            onclick="showApplicationDetail(<?php echo $app['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <?php if ($app['reviewed_by_name']): ?>
                                            Direview oleh: <?php echo $app['reviewed_by_name']; ?>
                                            <br>Tanggal: <?php echo formatDateIndo($app['reviewed_at'], true); ?>
                                        <?php endif; ?>
                                    </small>
                                    <button class="btn btn-outline-secondary btn-sm" 
                                            onclick="showApplicationDetail(<?php echo $app['id']; ?>)">
                                        <i class="fas fa-eye me-1"></i> Detail
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-center">
                <?php echo generatePagination($page, $totalPages, 'loan-approvals.php', ['status' => $status, 'search' => $search]); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Approval Modal -->
    <div class="modal fade" id="approvalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="approvalForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="approvalModalTitle"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                        <input type="hidden" name="action" id="approval_action">
                        <input type="hidden" name="application_id" id="approval_application_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Catatan:</label>
                            <textarea class="form-control" name="notes" rows="4" 
                                      placeholder="Berikan catatan untuk keputusan ini..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn" id="approval_submit_btn">Proses</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Thousand separator for auto-approve amount input
        (function() {
            const amountInput = document.querySelector('input[name="auto_approve_max_amount"]');
            if (amountInput) {
                amountInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/[^0-9]/g, '');
                    e.target.value = value ? new Intl.NumberFormat('id-ID').format(parseInt(value)) : '';
                });
            }
        })();
        
        function showApprovalModal(applicationId, action, applicantName) {
            document.getElementById('approval_application_id').value = applicationId;
            document.getElementById('approval_action').value = action;
            
            const modal = document.getElementById('approvalModal');
            const title = document.getElementById('approvalModalTitle');
            const submitBtn = document.getElementById('approval_submit_btn');
            
            if (action === 'approve') {
                title.textContent = 'Setujui Aplikasi - ' + applicantName;
                submitBtn.textContent = 'Setujui';
                submitBtn.className = 'btn btn-success';
            } else {
                title.textContent = 'Tolak Aplikasi - ' + applicantName;
                submitBtn.textContent = 'Tolak';
                submitBtn.className = 'btn btn-danger';
            }
            
            new bootstrap.Modal(modal).show();
        }
        
        function showApplicationDetail(applicationId) {
            // Implement application detail modal
            alert('Fitur detail aplikasi akan ditambahkan.');
        }
        
        // Form submission handling
        document.getElementById('approvalForm').addEventListener('submit', function(e) {
            const action = document.getElementById('approval_action').value;
            const applicantName = document.getElementById('approvalModalTitle').textContent.split(' - ')[1];
            
            let message = action === 'approve' ? 
                'Apakah Anda yakin ingin menyetujui aplikasi pinjaman ' + applicantName + '?' :
                'Apakah Anda yakin ingin menolak aplikasi pinjaman ' + applicantName + '?';
            
            if (!confirm(message)) {
                e.preventDefault();
                return;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('approval_submit_btn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Memproses...';
        });
    </script>
</body>
</html>