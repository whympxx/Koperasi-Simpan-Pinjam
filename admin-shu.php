<?php
/**
 * Admin - Perhitungan dan Laporan SHU
 * Koperasi Konsumen Karyawan PT. Puninar Jaya (KOPPUJA)
 */

require_once 'includes/init.php';

$auth->requireAuth();
$auth->requireRole(['admin', 'super_admin']);

$user = $auth->getCurrentUser();
$shuManager = new SHUManager();

$year = (int)($_REQUEST['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

$parseMoney = function ($value) {
    $raw = trim((string)$value);
    if ($raw === '') {
        return 0.0;
    }
    if (strpos($raw, ',') !== false) {
        $raw = str_replace('.', '', $raw);
        $raw = str_replace(',', '.', $raw);
    } else {
        $raw = str_replace('.', '', $raw);
    }
    return max(0, (float)$raw);
};

$savedPreview = $shuManager->getSavedPeriod($year);
$operatingExpense = isset($_REQUEST['operating_expense'])
    ? $parseMoney($_REQUEST['operating_expense'])
    : ($savedPreview ? (float)$savedPreview['operating_expense'] : 0);
$otherIncome = isset($_REQUEST['other_income'])
    ? $parseMoney($_REQUEST['other_income'])
    : ($savedPreview ? (float)$savedPreview['other_income'] : 0);
$notes = trim((string)($_POST['notes'] ?? ''));

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';

    if (!$auth->verifyCSRFToken($csrfToken)) {
        $error = 'Token keamanan tidak valid.';
    } elseif ($action === 'save' || $action === 'finalize') {
        $calc = $shuManager->calculate($year, $operatingExpense, $otherIncome);
        $status = $action === 'finalize' ? 'final' : 'draft';
        $result = $shuManager->savePeriod($calc, $user['id'], $status, $notes);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'reopen') {
        $result = $shuManager->reopenPeriod($year, $user['id']);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}

$calc = $shuManager->calculate($year, $operatingExpense, $otherIncome);
$saved = $calc['saved'];
$isFinal = $saved && $saved['status'] === 'final';
$companyName = $dbHelper->getSystemSetting('company_name') ?: APP_FULL_NAME;
$allocationTotal = $shuManager->getMainAllocationTotal($calc['percentages']);

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="laporan-shu-' . $year . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Laporan SHU ' . $companyName . ' Tahun Buku ' . $year], ';');
    fputcsv($out, ['SHU', $calc['shu_amount']], ';');
    fputcsv($out, []);
    fputcsv($out, ['NIK', 'Nama', 'Divisi', 'Simpanan Wajib', 'Bunga Dibayar', 'Denda', 'SHU Jasa Simpanan', 'SHU Jasa Pinjaman', 'Total SHU Anggota'], ';');
    foreach ($calc['members'] as $row) {
        fputcsv($out, [
            $row['nik'],
            $row['full_name'],
            $row['division_name'],
            $row['savings_balance'],
            $row['interest_paid'],
            $row['late_fee_paid'],
            $row['shu_simpanan'],
            $row['shu_pinjaman'],
            $row['shu_total'],
        ], ';');
    }
    fclose($out);
    exit;
}

renderAdminHeader('Laporan SHU - Admin', 'admin-shu.php', $user, 'Perhitungan & Laporan SHU');
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    @media print {
        .admin-sidebar, .admin-topbar, .no-print, .btn, form .btn { display: none !important; }
        .admin-main-content { margin-left: 0 !important; }
        .print-only { display: block !important; }
        .content-card { box-shadow: none !important; border: 1px solid #ddd !important; }
    }
    .print-only { display: none; }
    .shu-hero { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border-radius: 15px; }
</style>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show no-print"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show no-print"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="print-only text-center mb-4">
    <h4 class="mb-1"><?php echo htmlspecialchars($companyName); ?></h4>
    <h5>Laporan Perhitungan dan Pembagian SHU</h5>
    <p>Tahun Buku <?php echo (int)$year; ?> (<?php echo formatDateIndo($calc['start_date']); ?> s.d. <?php echo formatDateIndo($calc['end_date']); ?>)</p>
</div>

<div class="card content-card mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Tahun Buku</label>
                <input type="number" name="year" class="form-control" min="2000" max="2100" value="<?php echo (int)$year; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Biaya Operasional</label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="text" name="operating_expense" id="operating_expense" class="form-control js-rupiah" value="<?php echo $operatingExpense > 0 ? number_format($operatingExpense, 0, ',', '.') : ''; ?>" placeholder="0">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Pendapatan Lain</label>
                <div class="input-group">
                    <span class="input-group-text">Rp</span>
                    <input type="text" name="other_income" id="other_income" class="form-control js-rupiah" value="<?php echo $otherIncome > 0 ? number_format($otherIncome, 0, ',', '.') : ''; ?>" placeholder="0">
                </div>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-calculator me-1"></i> Hitung SHU</button>
            </div>
        </form>
        <div class="form-text mt-2">
            SHU = (bunga pinjaman terbayar + denda + pendapatan lain) &minus; biaya operasional.
            Alokasi persentase diatur di <a href="admin-settings.php">Pengaturan</a>.
        </div>
    </div>
</div>

<?php if ($allocationTotal != 100): ?>
    <div class="alert alert-warning no-print">
        Total alokasi SHU saat ini <?php echo number_format($allocationTotal, 1); ?>%. Sesuaikan di Pengaturan agar berjumlah 100%.
    </div>
<?php endif; ?>

<div class="shu-hero p-4 mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h6 class="text-white-50 mb-1">Sisa Hasil Usaha Tahun Buku <?php echo (int)$year; ?></h6>
            <h2 class="mb-1"><?php echo formatRupiah($calc['shu_amount']); ?></h2>
            <p class="mb-0 opacity-75">
                Periode <?php echo formatDateIndo($calc['start_date']); ?> &ndash; <?php echo formatDateIndo($calc['end_date']); ?>
                <?php if ($isFinal): ?>
                    <span class="badge bg-light text-success ms-2">Final</span>
                <?php elseif ($saved): ?>
                    <span class="badge bg-warning text-dark ms-2">Draf</span>
                <?php else: ?>
                    <span class="badge bg-light text-dark ms-2">Estimasi live</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="col-md-4 text-md-end no-print mt-3 mt-md-0">
            <button type="button" class="btn btn-light me-1" onclick="window.print()"><i class="fas fa-print me-1"></i> Cetak</button>
            <a class="btn btn-outline-light" href="admin-shu.php?year=<?php echo (int)$year; ?>&operating_expense=<?php echo urlencode(number_format($operatingExpense, 0, ',', '.')); ?>&other_income=<?php echo urlencode(number_format($otherIncome, 0, ',', '.')); ?>&export=csv">
                <i class="fas fa-file-csv me-1"></i> CSV
            </a>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card content-card p-3 h-100">
            <small class="text-muted">Bunga Pinjaman Terbayar</small>
            <h4 class="text-success mb-0"><?php echo formatRupiah($calc['interest_income']); ?></h4>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card content-card p-3 h-100">
            <small class="text-muted">Denda Keterlambatan</small>
            <h4 class="text-info mb-0"><?php echo formatRupiah($calc['late_fee_income']); ?></h4>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card content-card p-3 h-100">
            <small class="text-muted">Pendapatan Lain</small>
            <h4 class="mb-0"><?php echo formatRupiah($calc['other_income']); ?></h4>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card content-card p-3 h-100">
            <small class="text-muted">Biaya Operasional</small>
            <h4 class="text-danger mb-0"><?php echo formatRupiah($calc['operating_expense']); ?></h4>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-lg-5 mb-4">
        <div class="card content-card h-100">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-chart-pie text-primary me-2"></i> Pembagian SHU</h5></div>
            <div class="card-body">
                <div style="position:relative;height:240px;"><canvas id="shuChart"></canvas></div>
                <div class="table-responsive mt-3">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr><td>Cadangan (<?php echo $calc['percentages']['cadangan']; ?>%)</td><td class="text-end"><?php echo formatRupiah($calc['allocation']['cadangan']); ?></td></tr>
                            <tr>
                                <td>Jasa Anggota (<?php echo $calc['percentages']['jasa_anggota']; ?>%)</td>
                                <td class="text-end"><?php echo formatRupiah($calc['allocation']['jasa_anggota']); ?></td>
                            </tr>
                            <tr class="table-light">
                                <td class="ps-4">Jasa Simpanan (<?php echo $calc['percentages']['jasa_simpanan']; ?>% dari jasa anggota)</td>
                                <td class="text-end"><?php echo formatRupiah($calc['allocation']['jasa_simpanan']); ?></td>
                            </tr>
                            <tr class="table-light">
                                <td class="ps-4">Jasa Pinjaman (<?php echo $calc['percentages']['jasa_pinjaman']; ?>% dari jasa anggota)</td>
                                <td class="text-end"><?php echo formatRupiah($calc['allocation']['jasa_pinjaman']); ?></td>
                            </tr>
                            <tr><td>Dana Pengurus (<?php echo $calc['percentages']['dana_pengurus']; ?>%)</td><td class="text-end"><?php echo formatRupiah($calc['allocation']['dana_pengurus']); ?></td></tr>
                            <tr><td>Dana Karyawan (<?php echo $calc['percentages']['dana_karyawan']; ?>%)</td><td class="text-end"><?php echo formatRupiah($calc['allocation']['dana_karyawan']); ?></td></tr>
                            <tr><td>Dana Pendidikan (<?php echo $calc['percentages']['dana_pendidikan']; ?>%)</td><td class="text-end"><?php echo formatRupiah($calc['allocation']['dana_pendidikan']); ?></td></tr>
                            <tr><td>Dana Sosial (<?php echo $calc['percentages']['dana_sosial']; ?>%)</td><td class="text-end"><?php echo formatRupiah($calc['allocation']['dana_sosial']); ?></td></tr>
                            <tr class="fw-bold"><td>Total SHU</td><td class="text-end"><?php echo formatRupiah(max(0, $calc['shu_amount'])); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7 mb-4">
        <div class="card content-card h-100">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-equals text-primary me-2"></i> Ringkasan Perhitungan</h5></div>
            <div class="card-body">
                <table class="table">
                    <tr>
                        <td>Pendapatan bunga pinjaman</td>
                        <td class="text-end text-success">+ <?php echo formatRupiah($calc['interest_income']); ?></td>
                    </tr>
                    <tr>
                        <td>Pendapatan denda</td>
                        <td class="text-end text-success">+ <?php echo formatRupiah($calc['late_fee_income']); ?></td>
                    </tr>
                    <tr>
                        <td>Pendapatan lain-lain</td>
                        <td class="text-end text-success">+ <?php echo formatRupiah($calc['other_income']); ?></td>
                    </tr>
                    <tr>
                        <td>Total pendapatan</td>
                        <td class="text-end fw-bold"><?php echo formatRupiah($calc['total_income']); ?></td>
                    </tr>
                    <tr>
                        <td>Biaya operasional</td>
                        <td class="text-end text-danger">&minus; <?php echo formatRupiah($calc['operating_expense']); ?></td>
                    </tr>
                    <tr class="table-primary">
                        <th>SHU</th>
                        <th class="text-end"><?php echo formatRupiah($calc['shu_amount']); ?></th>
                    </tr>
                </table>
                <p class="text-muted small mb-2">
                    Dasar jasa simpanan: <?php echo formatRupiah($calc['total_savings_base']); ?>
                    (saldo simpanan wajib anggota).
                    Dasar jasa pinjaman: <?php echo formatRupiah($calc['total_interest_base']); ?>
                    (bunga yang sudah dibayar anggota).
                </p>
                <p class="text-muted small mb-0">
                    <?php echo (int)$calc['member_count']; ?> anggota berhak SHU.
                    Total dibagikan ke anggota: <strong><?php echo formatRupiah($calc['member_shu_total']); ?></strong>
                    (hanya dari porsi jasa anggota).
                </p>
                <?php if ($calc['shu_amount'] < 0): ?>
                    <div class="alert alert-danger mt-3 mb-0">SHU negatif. Tidak ada pembagian ke anggota sampai pendapatan melebihi beban.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card content-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-users text-primary me-2"></i> Rincian SHU per Anggota</h5>
        <small class="text-muted"><?php echo (int)$calc['member_count']; ?> anggota</small>
    </div>
    <div class="card-body p-0">
        <?php if (empty($calc['members'])): ?>
            <div class="text-center text-muted py-5">Belum ada data anggota / transaksi yang menjadi dasar SHU pada tahun ini.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>NIK</th>
                            <th>Nama</th>
                            <th>Divisi</th>
                            <th class="text-end">Simpanan Wajib</th>
                            <th class="text-end">Bunga Dibayar</th>
                            <th class="text-end">SHU Simpanan</th>
                            <th class="text-end">SHU Pinjaman</th>
                            <th class="text-end">Total SHU</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calc['members'] as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['nik']); ?></td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['division_name'] ?: '-'); ?></td>
                                <td class="text-end"><?php echo formatRupiah($row['savings_balance']); ?></td>
                                <td class="text-end"><?php echo formatRupiah($row['interest_paid']); ?></td>
                                <td class="text-end"><?php echo formatRupiah($row['shu_simpanan']); ?></td>
                                <td class="text-end"><?php echo formatRupiah($row['shu_pinjaman']); ?></td>
                                <td class="text-end fw-bold"><?php echo formatRupiah($row['shu_total']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold">
                            <td colspan="3">Total</td>
                            <td class="text-end"><?php echo formatRupiah($calc['total_savings_base']); ?></td>
                            <td class="text-end"><?php echo formatRupiah($calc['total_interest_base']); ?></td>
                            <td class="text-end"><?php echo formatRupiah($calc['allocation']['jasa_simpanan']); ?></td>
                            <td class="text-end"><?php echo formatRupiah($calc['allocation']['jasa_pinjaman']); ?></td>
                            <td class="text-end"><?php echo formatRupiah($calc['member_shu_total']); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card content-card mb-4 no-print">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-save text-primary me-2"></i> Simpan / Kunci Laporan</h5></div>
    <div class="card-body">
        <?php if ($saved): ?>
            <p class="text-muted">
                Terakhir disimpan: <?php echo formatDateIndo($saved['updated_at'] ?? $saved['generated_at'], true); ?>
                oleh <?php echo htmlspecialchars($saved['generated_by_name'] ?: '-'); ?>
                (status: <?php echo $saved['status'] === 'final' ? 'Final' : 'Draf'; ?>).
            </p>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
            <input type="hidden" name="year" value="<?php echo (int)$year; ?>">
            <input type="hidden" name="operating_expense" value="<?php echo htmlspecialchars((string)$operatingExpense); ?>">
            <input type="hidden" name="other_income" value="<?php echo htmlspecialchars((string)$otherIncome); ?>">
            <div class="mb-3">
                <label class="form-label">Catatan</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Opsional, misalnya keputusan RAT"><?php echo htmlspecialchars($saved ? ($saved['notes'] ?? '') : $notes); ?></textarea>
            </div>
            <?php if ($isFinal): ?>
                <button type="submit" name="action" value="reopen" class="btn btn-outline-warning" onclick="return confirm('Buka kunci laporan SHU tahun ini?');">
                    <i class="fas fa-unlock me-1"></i> Buka Kunci
                </button>
            <?php else: ?>
                <button type="submit" name="action" value="save" class="btn btn-primary me-2">
                    <i class="fas fa-save me-1"></i> Simpan Draf
                </button>
                <button type="submit" name="action" value="finalize" class="btn btn-success" onclick="return confirm('Kunci laporan SHU tahun <?php echo (int)$year; ?> sebagai final?');">
                    <i class="fas fa-lock me-1"></i> Kunci sebagai Final
                </button>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
    document.querySelectorAll('.js-rupiah').forEach(function(el) {
        el.addEventListener('input', function(e) {
            let v = e.target.value.replace(/[^0-9]/g, '');
            e.target.value = v ? new Intl.NumberFormat('id-ID').format(parseInt(v, 10)) : '';
        });
    });

    new Chart(document.getElementById('shuChart'), {
        type: 'doughnut',
        data: {
            labels: ['Cadangan', 'Jasa Anggota', 'Dana Pengurus', 'Dana Karyawan', 'Dana Pendidikan', 'Dana Sosial'],
            datasets: [{
                data: [
                    <?php echo (float)$calc['allocation']['cadangan']; ?>,
                    <?php echo (float)$calc['allocation']['jasa_anggota']; ?>,
                    <?php echo (float)$calc['allocation']['dana_pengurus']; ?>,
                    <?php echo (float)$calc['allocation']['dana_karyawan']; ?>,
                    <?php echo (float)$calc['allocation']['dana_pendidikan']; ?>,
                    <?php echo (float)$calc['allocation']['dana_sosial']; ?>
                ],
                backgroundColor: ['#667eea', '#28a745', '#ffc107', '#17a2b8', '#6f42c1', '#fd7e14']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });
</script>

<?php renderAdminFooter(); ?>
