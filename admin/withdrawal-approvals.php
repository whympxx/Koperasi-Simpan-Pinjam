<?php
require_once '../includes/init.php';

// Require authentication and admin role
$auth->requireAuth();
$auth->requireRole(['admin', 'super_admin']);

$user = $auth->getCurrentUser();
$savingsManager = new SavingsManager();

$error = '';
$success = '';

// Handle approval/rejection
if ($_POST) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    $transactionId = (int)($_POST['transaction_id'] ?? 0);
    $notes = SecurityHelper::sanitize($_POST['notes'] ?? '');
    
    if (!$auth->verifyCSRFToken($csrfToken)) {
        $error = 'Token keamanan tidak valid.';
    } elseif (!in_array($action, ['approve', 'reject']) || empty($transactionId)) {
        $error = 'Data tidak valid.';
    } else {
        if ($action === 'approve') {
            $result = $savingsManager->approveWithdrawal($transactionId, $user['id'], $notes);
        } else {
            $result = $savingsManager->rejectWithdrawal($transactionId, $user['id'], $notes);
        }
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

// Pagination and filters
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;
$status = $_GET['status'] ?? 'pending';
$search = $_GET['search'] ?? '';

// Build where clause
$whereConditions = ["t.type = 'savings_withdrawal'"];
$params = [];

if ($status !== 'all') {
    $whereConditions[] = 't.status = ?';
    $params[] = $status;
}

if ($search) {
    $whereConditions[] = '(u.full_name LIKE ? OR u.nik LIKE ? OR t.reference_number LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get withdrawal transactions
$withdrawals = $db->select(
    "SELECT t.*, u.full_name, u.nik, u.phone, u.email,
            sa.account_number, sa.balance as current_balance,
            d.name as division_name, pu.full_name as processed_by_name
     FROM transactions t
     JOIN users u ON t.user_id = u.id
     LEFT JOIN savings_accounts sa ON t.related_account_id = sa.id
     LEFT JOIN divisions d ON u.division_id = d.id
     LEFT JOIN users pu ON t.processed_by = pu.id
     $whereClause
     ORDER BY t.created_at DESC
     LIMIT $limit OFFSET $offset",
    $params
);

// Get total count for pagination
$totalCount = $db->selectOne(
    "SELECT COUNT(*) as total FROM transactions t
     JOIN users u ON t.user_id = u.id
     $whereClause",
    $params
)['total'];

$totalPages = ceil($totalCount / $limit);

// Get statistics
$stats = $db->selectOne(
    "SELECT 
        COUNT(*) as total_withdrawals,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as rejected_count,
        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as approved_amount,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount
     FROM transactions 
     WHERE type = 'savings_withdrawal'"
);

$pageTitle = 'Persetujuan Penarikan - Admin Dashboard';
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
    
    <style>
        body { background-color: #f8f9fa; }
        
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
        
        .stats-card:hover { transform: translateY(-5px); }
        
        .withdrawal-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border-left: 4px solid #e9ecef;
        }
        
        .withdrawal-card.pending { border-left-color: #ffc107; }
        .withdrawal-card.completed { border-left-color: #28a745; }
        .withdrawal-card.failed { border-left-color: #dc3545; }
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
                <a class="nav-link" href="loan-approvals.php">
                    <i class="fas fa-hand-holding-usd me-2"></i> Persetujuan Pinjaman
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="withdrawal-approvals.php">
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
                <h2 class="mb-0">Persetujuan Penarikan</h2>
                <p class="text-muted">Kelola permintaan penarikan tabungan dari nasabah</p>
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
                    <h4 class="mb-0"><?php echo $stats['total_withdrawals']; ?></h4>
                    <small class="text-muted">Total Penarikan</small>
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
            <div class="col-md-2">
                <div class="stats-card p-3 text-center">
                    <i class="fas fa-hourglass-half fa-2x text-warning mb-2"></i>
                    <h4 class="mb-0"><?php echo formatRupiah($stats['pending_amount'], false); ?></h4>
                    <small class="text-muted">Pending Amount</small>
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
                            <a href="?status=completed" class="btn btn-sm <?php echo $status === 'completed' ? 'btn-success' : 'btn-outline-success'; ?>">
                                Disetujui
                            </a>
                            <a href="?status=failed" class="btn btn-sm <?php echo $status === 'failed' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                                Ditolak
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 offset-md-4">
                        <form method="GET" class="d-flex">
                            <input type="hidden" name="status" value="<?php echo $status; ?>">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Cari nama, NIK, atau nomor referensi..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Withdrawals List -->
        <div class="row">
            <?php if (empty($withdrawals)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                        <h5>Tidak Ada Penarikan</h5>
                        <p class="text-muted">Belum ada permintaan penarikan yang tersedia.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($withdrawals as $withdrawal): ?>
                    <div class="col-lg-6 mb-4">
                        <div class="withdrawal-card <?php echo $withdrawal['status']; ?> p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h6 class="mb-1"><?php echo $withdrawal['reference_number']; ?></h6>
                                    <p class="mb-0 text-muted"><?php echo formatDateIndo($withdrawal['transaction_date'], true); ?></p>
                                </div>
                                <div>
                                    <?php echo getStatusBadge($withdrawal['status'], 'transaction'); ?>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-8">
                                    <strong><?php echo $withdrawal['full_name']; ?></strong><br>
                                    <small class="text-muted">
                                        NIK: <?php echo $withdrawal['nik']; ?><br>
                                        Rekening: <?php echo $withdrawal['account_number']; ?><br>
                                        <?php echo $withdrawal['phone']; ?>
                                    </small>
                                </div>
                                <div class="col-4 text-end">
                                    <h5 class="mb-0 text-primary"><?php echo formatRupiah($withdrawal['amount']); ?></h5>
                                    <small class="text-muted">Penarikan</small>
                                </div>
                            </div>
                            
                            <?php 
                            $metadata = json_decode($withdrawal['metadata'] ?? '{}', true);
                            if (!empty($metadata) && isset($metadata['bank_name'])): 
                            ?>
                            <div class="mb-3">
                                <small class="text-muted">Detail Transfer:</small><br>
                                <div class="row">
                                    <div class="col-4">
                                        <strong><?php echo $metadata['bank_name']; ?></strong>
                                    </div>
                                    <div class="col-4">
                                        <?php echo $metadata['account_number']; ?>
                                    </div>
                                    <div class="col-4">
                                        <?php echo $metadata['account_name']; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Saldo Sebelum</small><br>
                                    <strong><?php echo formatRupiah($withdrawal['balance_before']); ?></strong>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Saldo Sesudah</small><br>
                                    <strong><?php echo formatRupiah($withdrawal['balance_after']); ?></strong>
                                </div>
                            </div>
                            
                            <?php if ($withdrawal['description']): ?>
                                <div class="mb-3">
                                    <small class="text-muted">Keterangan:</small><br>
                                    <span><?php echo $withdrawal['description']; ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($withdrawal['status'] === 'pending'): ?>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-success btn-sm flex-fill" 
                                            onclick="showApprovalModal(<?php echo $withdrawal['id']; ?>, 'approve', '<?php echo addslashes($withdrawal['full_name']); ?>', '<?php echo formatRupiah($withdrawal['amount']); ?>')">
                                        <i class="fas fa-check me-1"></i> Setujui
                                    </button>
                                    <button class="btn btn-danger btn-sm flex-fill" 
                                            onclick="showApprovalModal(<?php echo $withdrawal['id']; ?>, 'reject', '<?php echo addslashes($withdrawal['full_name']); ?>', '<?php echo formatRupiah($withdrawal['amount']); ?>')">
                                        <i class="fas fa-times me-1"></i> Tolak
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <?php if ($withdrawal['processed_by_name']): ?>
                                            Diproses oleh: <?php echo $withdrawal['processed_by_name']; ?>
                                        <?php endif; ?>
                                    </small>
                                    <span class="badge bg-<?php echo $withdrawal['status'] === 'completed' ? 'success' : 'danger'; ?>">
                                        <?php echo $withdrawal['status'] === 'completed' ? 'Telah Ditransfer' : 'Ditolak'; ?>
                                    </span>
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
                <?php echo generatePagination($page, $totalPages, 'withdrawal-approvals.php', ['status' => $status, 'search' => $search]); ?>
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
                        <input type="hidden" name="transaction_id" id="approval_transaction_id">
                        
                        <div class="alert alert-info" id="approval_info"></div>
                        
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
        function showApprovalModal(transactionId, action, customerName, amount) {
            document.getElementById('approval_transaction_id').value = transactionId;
            document.getElementById('approval_action').value = action;
            
            const modal = document.getElementById('approvalModal');
            const title = document.getElementById('approvalModalTitle');
            const submitBtn = document.getElementById('approval_submit_btn');
            const info = document.getElementById('approval_info');
            
            if (action === 'approve') {
                title.textContent = 'Setujui Penarikan - ' + customerName;
                submitBtn.textContent = 'Setujui & Transfer';
                submitBtn.className = 'btn btn-success';
                info.innerHTML = '<i class="fas fa-info-circle me-2"></i>Penarikan sebesar <strong>' + amount + '</strong> akan segera ditransfer ke rekening nasabah.';
            } else {
                title.textContent = 'Tolak Penarikan - ' + customerName;
                submitBtn.textContent = 'Tolak';
                submitBtn.className = 'btn btn-danger';
                info.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Penarikan sebesar <strong>' + amount + '</strong> akan ditolak dan saldo akan dikembalikan.';
                info.className = 'alert alert-warning';
            }
            
            new bootstrap.Modal(modal).show();
        }
        
        // Form submission handling
        document.getElementById('approvalForm').addEventListener('submit', function(e) {
            const action = document.getElementById('approval_action').value;
            const customerName = document.getElementById('approvalModalTitle').textContent.split(' - ')[1];
            
            let message = action === 'approve' ? 
                'Apakah Anda yakin ingin menyetujui penarikan dari ' + customerName + '?' :
                'Apakah Anda yakin ingin menolak penarikan dari ' + customerName + '?';
            
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