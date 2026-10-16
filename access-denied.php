<?php
/**
 * Access Denied page
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

require_once 'includes/init.php';

$user = $auth->getCurrentUser();
$homeLink = 'login.php';
if ($user) {
    $homeLink = in_array($user['role'], ['admin', 'super_admin']) ? 'admin-dashboard.php' : 'dashboard.php';
}

$pageTitle = 'Akses Ditolak - ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh;">
    <div class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="text-center text-white">
            <i class="fas fa-lock fa-5x mb-4"></i>
            <h1 class="display-4 fw-bold">403</h1>
            <h3>Akses Ditolak</h3>
            <p class="lead">Anda tidak memiliki izin untuk mengakses halaman ini.</p>
            <a href="<?php echo $homeLink; ?>" class="btn btn-light btn-lg mt-3">
                <i class="fas fa-home me-2"></i> Kembali ke Beranda
            </a>
        </div>
    </div>
</body>
</html>
