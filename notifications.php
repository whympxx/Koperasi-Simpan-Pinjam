<?php
require_once 'includes/init.php';

// Require authentication and nasabah role
$auth->requireAuth();
$auth->requireRole('nasabah');

$user = $auth->getCurrentUser();

$error = '';
$success = '';

// Handle mark as read
if ($_POST['action'] ?? '' === 'mark_read' && isset($_POST['notification_id'])) {
    $notificationId = (int)$_POST['notification_id'];
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if ($auth->verifyCSRFToken($csrfToken)) {
        $result = $db->update(
            "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?",
            [$notificationId, $user['id']]
        );
        
        if ($result) {
            $success = 'Notifikasi berhasil ditandai sudah dibaca.';
        }
    }
}

// Handle mark all as read
if ($_POST['action'] ?? '' === 'mark_all_read') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if ($auth->verifyCSRFToken($csrfToken)) {
        $result = $db->update(
            "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0",
            [$user['id']]
        );
        
        if ($result) {
            $success = 'Semua notifikasi berhasil ditandai sudah dibaca.';
        }
    }
}

// Handle delete notification
if ($_POST['action'] ?? '' === 'delete' && isset($_POST['notification_id'])) {
    $notificationId = (int)$_POST['notification_id'];
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if ($auth->verifyCSRFToken($csrfToken)) {
        $result = $db->delete(
            "DELETE FROM notifications WHERE id = ? AND user_id = ?",
            [$notificationId, $user['id']]
        );
        
        if ($result) {
            $success = 'Notifikasi berhasil dihapus.';
        }
    }
}

// Pagination
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Get filter
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build where clause
$whereConditions = ['user_id = ?'];
$params = [$user['id']];

if ($filter === 'unread') {
    $whereConditions[] = 'is_read = 0';
} elseif ($filter === 'read') {
    $whereConditions[] = 'is_read = 1';
} elseif ($filter !== 'all') {
    $whereConditions[] = 'category = ?';
    $params[] = $filter;
}

if ($search) {
    $whereConditions[] = '(title LIKE ? OR message LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get notifications
$notifications = $db->select(
    "SELECT * FROM notifications 
     $whereClause 
     ORDER BY created_at DESC 
     LIMIT $limit OFFSET $offset",
    $params
);

// Get total count for pagination
$totalCount = $db->selectOne(
    "SELECT COUNT(*) as total FROM notifications $whereClause",
    $params
)['total'];

$totalPages = ceil($totalCount / $limit);

// Get unread count
$unreadCount = $db->selectOne(
    "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
    [$user['id']]
)['count'];

// Get notification categories
$categories = $db->select(
    "SELECT DISTINCT category, COUNT(*) as count 
     FROM notifications 
     WHERE user_id = ? 
     GROUP BY category 
     ORDER BY category",
    [$user['id']]
);

$pageTitle = 'Notifikasi - ' . APP_NAME;
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
    
    <style>
        .notification-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .notification-card:hover {
            background-color: #f8f9fa;
            border-left-color: #667eea;
        }
        
        .notification-card.unread {
            background-color: rgba(102, 126, 234, 0.05);
            border-left-color: #667eea;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .notification-icon.info { background-color: rgba(23, 162, 184, 0.1); color: #17a2b8; }
        .notification-icon.success { background-color: rgba(40, 167, 69, 0.1); color: #28a745; }
        .notification-icon.warning { background-color: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .notification-icon.danger { background-color: rgba(220, 53, 69, 0.1); color: #dc3545; }
        
        .filter-pills .nav-link {
            border-radius: 25px;
            margin-right: 0.5rem;
            border: 1px solid #e9ecef;
        }
        
        .filter-pills .nav-link.active {
            background-color: #667eea;
            border-color: #667eea;
            color: white;
        }
    </style>
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
                        <a class="nav-link" href="transactions.php">
                            <i class="fas fa-exchange-alt me-1"></i> Transaksi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="notifications.php">
                            <i class="fas fa-bell me-1"></i> 
                            Notifikasi
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $unreadCount; ?></span>
                            <?php endif; ?>
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

    <div class="container py-4">
        <!-- Breadcrumb -->
        <?php echo generateBreadcrumb([
            'Dashboard' => 'dashboard.php',
            'Notifikasi' => ''
        ]); ?>
        
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <i class="fas fa-bell text-primary me-3 fa-2x"></i>
                    <div>
                        <h2 class="mb-0">Notifikasi</h2>
                        <p class="text-muted mb-0">Kelola notifikasi dan pengumuman penting</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <?php if ($unreadCount > 0): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-check-double me-1"></i>
                            Tandai Semua Dibaca
                        </button>
                    </form>
                <?php endif; ?>
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

        <!-- Filters and Search -->
        <div class="row mb-4">
            <div class="col-md-8">
                <ul class="nav filter-pills">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" 
                           href="?filter=all">
                            Semua (<?php echo $totalCount; ?>)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $filter === 'unread' ? 'active' : ''; ?>" 
                           href="?filter=unread">
                            Belum Dibaca (<?php echo $unreadCount; ?>)
                        </a>
                    </li>
                    <?php foreach ($categories as $cat): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $filter === $cat['category'] ? 'active' : ''; ?>" 
                               href="?filter=<?php echo $cat['category']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $cat['category'])); ?> (<?php echo $cat['count']; ?>)
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="col-md-4">
                <form method="GET" class="d-flex">
                    <input type="hidden" name="filter" value="<?php echo $filter; ?>">
                    <input type="text" class="form-control" name="search" 
                           placeholder="Cari notifikasi..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Notifications List -->
        <div class="row">
            <div class="col-12">
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                        <h5>Tidak Ada Notifikasi</h5>
                        <p class="text-muted">Belum ada notifikasi yang tersedia.</p>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body p-0">
                            <?php foreach ($notifications as $index => $notification): ?>
                                <div class="notification-card p-3 border-bottom <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <div class="notification-icon <?php echo $notification['type']; ?>">
                                                <?php echo getNotificationIcon($notification['type']); ?>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1 <?php echo !$notification['is_read'] ? 'fw-bold' : ''; ?>">
                                                        <?php echo htmlspecialchars($notification['title']); ?>
                                                        <?php if (!$notification['is_read']): ?>
                                                            <span class="badge bg-primary ms-2">Baru</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <p class="mb-1 text-muted"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo timeAgo($notification['created_at']); ?>
                                                    </small>
                                                </div>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                            type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <?php if (!$notification['is_read']): ?>
                                                            <li>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                                                                    <input type="hidden" name="action" value="mark_read">
                                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                                    <button type="submit" class="dropdown-item">
                                                                        <i class="fas fa-check me-2"></i>Tandai Dibaca
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        <?php endif; ?>
                                                        <li>
                                                            <form method="POST" class="d-inline" 
                                                                  onsubmit="return confirm('Yakin ingin menghapus notifikasi ini?')">
                                                                <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                                <button type="submit" class="dropdown-item text-danger">
                                                                    <i class="fas fa-trash me-2"></i>Hapus
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="mt-4">
                            <?php echo generatePagination($page, $totalPages, 'notifications.php', ['filter' => $filter, 'search' => $search]); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-refresh notifications every 5 minutes
        setInterval(function() {
            if (!document.hidden) {
                window.location.reload();
            }
        }, 300000); // 5 minutes
        
        // Mark notification as read when clicked
        document.querySelectorAll('.notification-card').forEach(function(card) {
            card.addEventListener('click', function(e) {
                if (e.target.closest('.dropdown') || e.target.closest('form')) {
                    return; // Don't mark as read if clicking dropdown or form
                }
                
                if (card.classList.contains('unread')) {
                    const notificationId = card.querySelector('input[name="notification_id"]').value;
                    const csrfToken = card.querySelector('input[name="csrf_token"]').value;
                    
                    // Send AJAX request to mark as read
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=mark_read&notification_id=${notificationId}&csrf_token=${csrfToken}`
                    }).then(() => {
                        card.classList.remove('unread');
                        const badge = card.querySelector('.badge');
                        if (badge) badge.remove();
                        
                        // Update unread count
                        const unreadBadge = document.querySelector('.navbar .badge');
                        if (unreadBadge) {
                            let count = parseInt(unreadBadge.textContent) - 1;
                            if (count > 0) {
                                unreadBadge.textContent = count;
                            } else {
                                unreadBadge.remove();
                            }
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>