<?php
/**
 * Laporan SHU Anggota
 * Koperasi Konsumen Karyawan PT. Puninar Jaya (KOPPUJA)
 */

require_once 'includes/init.php';

$auth->requireAuth();
$auth->requireRole('nasabah');

$user = $auth->getCurrentUser();
$shuManager = new SHUManager();

$year = (int)($_GET['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

$share = $shuManager->getMemberShare($user['id'], $year);
$period = $share['period'];
$saved = $share['saved'];
$companyName = $dbHelper->getSystemSetting('company_name') ?: APP_FULL_NAME;

$pageTitle = 'Laporan SHU - ' . APP_NAME;
$currentPage = 'shu.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .shu-hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border-radius: 15px; }
        @media print {
            .navbar, .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark no-print" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php"><i class="fas fa-building"></i> <strong><?php echo APP_NAME; ?></strong></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-1"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="savings.php"><i class="fas fa-piggy-bank me-1"></i> Tabungan</a></li>
                    <li class="nav-item"><a class="nav-link" href="loans.php"><i class="fas fa-hand-holding-usd me-1"></i> Pinjaman</a></li>
                    <li class="nav-item"><a class="nav-link" href="transactions.php"><i class="fas fa-exchange-alt me-1"></i> Transaksi</a></li>
                    <li class="nav-item"><a class="nav-link active" href="shu.php"><i class="fas fa-coins me-1"></i> SHU</a></li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-arrow-left me-1"></i> Kembali</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <h2 class="mb-1"><i class="fas fa-coins me-2"></i>Laporan SHU</h2>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($companyName); ?></p>
            </div>
            <button class="btn btn-outline-primary" onclick="window.print()"><i class="fas fa-print me-1"></i> Cetak</button>
        </div>

        <div class="card mb-4 no-print">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label">Tahun Buku</label>
                        <input type="number" name="year" class="form-control" min="2000" max="2100" value="<?php echo (int)$year; ?>">
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter me-1"></i> Tampilkan</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="shu-hero p-4 mb-4">
            <small class="text-white-50">Perkiraan SHU Anda Tahun Buku <?php echo (int)$year; ?></small>
            <h2 class="mb-1"><?php echo formatRupiah($share['shu_total']); ?></h2>
            <p class="mb-0 opacity-75">
                Periode <?php echo formatDateIndo($period['start_date']); ?> &ndash; <?php echo formatDateIndo($period['end_date']); ?>
                <?php if ($saved && $saved['status'] === 'final'): ?>
                    <span class="badge bg-light text-success ms-2">Sudah dikunci pengurus</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark ms-2">Estimasi (belum final)</span>
                <?php endif; ?>
            </p>
        </div>

        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card p-3 h-100">
                    <small class="text-muted">SHU Jasa Simpanan</small>
                    <h4 class="text-success mb-0"><?php echo formatRupiah($share['shu_simpanan']); ?></h4>
                    <small class="text-muted">Dari saldo simpanan wajib <?php echo formatRupiah($share['savings_balance']); ?></small>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card p-3 h-100">
                    <small class="text-muted">SHU Jasa Pinjaman</small>
                    <h4 class="text-primary mb-0"><?php echo formatRupiah($share['shu_pinjaman']); ?></h4>
                    <small class="text-muted">Dari bunga yang Anda bayar <?php echo formatRupiah($share['interest_paid']); ?></small>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card p-3 h-100">
                    <small class="text-muted">Total SHU Koperasi</small>
                    <h4 class="mb-0"><?php echo formatRupiah($period['shu_amount']); ?></h4>
                    <small class="text-muted">Porsi jasa anggota <?php echo formatRupiah($period['allocation']['jasa_anggota']); ?></small>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white"><strong>Bagaimana SHU Dihitung</strong></div>
            <div class="card-body">
                <ol class="mb-3">
                    <li>Pendapatan koperasi = bunga pinjaman yang sudah dibayar + denda + pendapatan lain.</li>
                    <li>SHU = pendapatan dikurangi biaya operasional.</li>
                    <li>Bagian anggota diambil dari porsi <em>jasa anggota</em>, lalu dibagi:
                        <ul>
                            <li>Jasa simpanan, sebanding dengan saldo simpanan wajib Anda.</li>
                            <li>Jasa pinjaman, sebanding dengan bunga pinjaman yang Anda bayar.</li>
                        </ul>
                    </li>
                </ol>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tr><td>Bunga pinjaman koperasi</td><td class="text-end"><?php echo formatRupiah($period['interest_income']); ?></td></tr>
                        <tr><td>Denda</td><td class="text-end"><?php echo formatRupiah($period['late_fee_income']); ?></td></tr>
                        <tr><td>Pendapatan lain</td><td class="text-end"><?php echo formatRupiah($period['other_income']); ?></td></tr>
                        <tr><td>Biaya operasional</td><td class="text-end"><?php echo formatRupiah($period['operating_expense']); ?></td></tr>
                        <tr class="fw-bold"><td>SHU koperasi</td><td class="text-end"><?php echo formatRupiah($period['shu_amount']); ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
