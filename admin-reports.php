<?php
/**
 * Admin - Laporan
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

require_once 'includes/init.php';

$auth->requireAuth();
$auth->requireRole(['admin', 'super_admin']);

$user = $auth->getCurrentUser();
$shuManager = new SHUManager();
$shuYear = (int)date('Y');
$savedShu = $shuManager->getSavedPeriod($shuYear);
$shuCalc = $shuManager->calculate(
    $shuYear,
    $savedShu ? (float)$savedShu['operating_expense'] : 0,
    $savedShu ? (float)$savedShu['other_income'] : 0
);

// Date range
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Summary within range
$summary = $db->selectOne(
    "SELECT
        SUM(CASE WHEN type = 'savings_deposit' THEN amount ELSE 0 END) as deposit,
        SUM(CASE WHEN type = 'savings_withdrawal' THEN amount ELSE 0 END) as withdrawal,
        SUM(CASE WHEN type = 'loan_disbursement' THEN amount ELSE 0 END) as disbursement,
        SUM(CASE WHEN type = 'loan_payment' THEN amount ELSE 0 END) as payment,
        COUNT(*) as total_tx
     FROM transactions
     WHERE status = 'completed' AND transaction_date BETWEEN ? AND ?",
    [$startDate, $endDate]
);

// Overall portfolio
$portfolio = $db->selectOne(
    "SELECT
        (SELECT COALESCE(SUM(balance),0) FROM savings_accounts WHERE status='active') as total_savings,
        (SELECT COALESCE(SUM(remaining_balance),0) FROM active_loans WHERE status='active') as total_outstanding,
        (SELECT COUNT(*) FROM active_loans WHERE status='active') as active_loans,
        (SELECT COUNT(*) FROM loan_applications WHERE status='pending') as pending_apps"
);

// Monthly chart data (last 6 months)
$monthly = $db->select(
    "SELECT DATE_FORMAT(transaction_date, '%Y-%m') as month, type, SUM(amount) as total
     FROM transactions
     WHERE status='completed' AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(transaction_date, '%Y-%m'), type
     ORDER BY month ASC"
);

renderAdminHeader('Laporan - Admin', 'admin-reports.php', $user, 'Laporan Keuangan');
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="card content-card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:#fff;">
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h6 class="text-white-50 mb-1">Sisa Hasil Usaha (SHU) Tahun Buku <?php echo $shuYear; ?></h6>
                <h2 class="mb-1"><?php echo formatRupiah($shuCalc['shu_amount']); ?></h2>
                <p class="mb-0 opacity-75">
                    Pendapatan <?php echo formatRupiah($shuCalc['total_income']); ?>
                    &minus; Beban <?php echo formatRupiah($shuCalc['total_expense']); ?>
                    · Jasa anggota <?php echo formatRupiah($shuCalc['allocation']['jasa_anggota']); ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="admin-shu.php?year=<?php echo $shuYear; ?>" class="btn btn-light">
                    <i class="fas fa-coins me-1"></i> Lihat Laporan SHU
                </a>
            </div>
        </div>
    </div>
</div>

<div class="card content-card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Dari Tanggal</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Sampai Tanggal</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i> Terapkan</button>
            </div>
        </form>
    </div>
</div>

<h6 class="text-muted mb-3">Ringkasan Periode <?php echo formatDateIndo($startDate); ?> &ndash; <?php echo formatDateIndo($endDate); ?> (<?php echo (int)$summary['total_tx']; ?> transaksi)</h6>
<div class="row mb-4">
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-success p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Setoran Tabungan</h6><h5 class="mb-0"><?php echo formatRupiah($summary['deposit'] ?? 0); ?></h5><i class="fas fa-arrow-down card-icon"></i></div></div></div>
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-danger p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Penarikan</h6><h5 class="mb-0"><?php echo formatRupiah($summary['withdrawal'] ?? 0); ?></h5><i class="fas fa-arrow-up card-icon"></i></div></div></div>
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-primary p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Pencairan Pinjaman</h6><h5 class="mb-0"><?php echo formatRupiah($summary['disbursement'] ?? 0); ?></h5><i class="fas fa-hand-holding-usd card-icon"></i></div></div></div>
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-info p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Pembayaran Pinjaman</h6><h5 class="mb-0"><?php echo formatRupiah($summary['payment'] ?? 0); ?></h5><i class="fas fa-money-check card-icon"></i></div></div></div>
</div>

<div class="row mb-4">
    <div class="col-md-3 mb-3"><div class="card content-card p-3 text-center"><small class="text-muted">Total Saldo Tabungan</small><h4 class="text-success mb-0"><?php echo formatRupiah($portfolio['total_savings']); ?></h4></div></div>
    <div class="col-md-3 mb-3"><div class="card content-card p-3 text-center"><small class="text-muted">Total Outstanding</small><h4 class="text-warning mb-0"><?php echo formatRupiah($portfolio['total_outstanding']); ?></h4></div></div>
    <div class="col-md-3 mb-3"><div class="card content-card p-3 text-center"><small class="text-muted">Pinjaman Aktif</small><h4 class="text-primary mb-0"><?php echo (int)$portfolio['active_loans']; ?></h4></div></div>
    <div class="col-md-3 mb-3"><div class="card content-card p-3 text-center"><small class="text-muted">Pengajuan Pending</small><h4 class="text-danger mb-0"><?php echo (int)$portfolio['pending_apps']; ?></h4></div></div>
</div>

<div class="card content-card">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-chart-line text-primary me-2"></i> Tren Transaksi 6 Bulan Terakhir</h5></div>
    <div class="card-body">
        <div style="position: relative; height: 320px;"><canvas id="reportChart"></canvas></div>
    </div>
</div>

<script>
    const raw = <?php echo json_encode($monthly); ?>;
    const grouped = {};
    raw.forEach(r => {
        if (!grouped[r.month]) grouped[r.month] = { savings_deposit: 0, savings_withdrawal: 0, loan_disbursement: 0, loan_payment: 0 };
        grouped[r.month][r.type] = parseFloat(r.total);
    });
    const months = Object.keys(grouped).sort();
    const labelMonths = months.map(m => { const d = new Date(m + '-01'); return d.toLocaleDateString('id-ID', { month: 'short', year: 'numeric' }); });
    new Chart(document.getElementById('reportChart'), {
        type: 'bar',
        data: {
            labels: labelMonths,
            datasets: [
                { label: 'Setoran', data: months.map(m => grouped[m].savings_deposit), backgroundColor: '#28a745' },
                { label: 'Penarikan', data: months.map(m => grouped[m].savings_withdrawal), backgroundColor: '#dc3545' },
                { label: 'Pencairan', data: months.map(m => grouped[m].loan_disbursement), backgroundColor: '#667eea' },
                { label: 'Pembayaran', data: months.map(m => grouped[m].loan_payment), backgroundColor: '#17a2b8' }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { callback: v => 'Rp ' + v.toLocaleString('id-ID') } } } }
    });
</script>

<?php renderAdminFooter(); ?>
