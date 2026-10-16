<?php
/**
 * Admin - Pengaturan Sistem
 * KOPPUJA - Sesuai ketentuan RAT Tahun Buku 2025
 */

require_once 'includes/init.php';

$auth->requireAuth();
$auth->requireRole(['admin', 'super_admin']);

$user = $auth->getCurrentUser();

$error = '';
$success = '';

if ($_POST) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $form = $_POST['form'] ?? '';

    if (!$auth->verifyCSRFToken($csrfToken)) {
        $error = 'Token keamanan tidak valid.';
    } elseif ($form === 'general') {
        $interestRate = (float)($_POST['loan_interest_rate'] ?? 0);
        $maxTerm = (int)($_POST['max_loan_term'] ?? 0);
        $standardTerm = (int)($_POST['max_loan_term_standard'] ?? 0);
        $multiplier = (float)($_POST['max_loan_multiplier'] ?? 0);
        $multiplierLimited = (float)($_POST['max_loan_multiplier_limited'] ?? 0);
        $minSavings = (float)str_replace(['.', ','], ['', '.'], $_POST['min_mandatory_savings'] ?? '0');
        $minEmployment = (int)($_POST['min_employment_months'] ?? 0);
        $lateFee = (float)($_POST['late_fee_percentage'] ?? 0);
        $companyName = SecurityHelper::sanitize($_POST['company_name'] ?? '');

        if ($interestRate < 0 || $lateFee < 0) {
            $error = 'Nilai persentase tidak boleh negatif.';
        } elseif ($maxTerm < 1 || $maxTerm > 18) {
            $error = 'Maksimal tenor kebijakan harus antara 1-18 bulan.';
        } elseif ($standardTerm < 1 || $standardTerm > $maxTerm) {
            $error = 'Tenor standar harus antara 1 bulan dan tidak melebihi tenor kebijakan.';
        } elseif ($multiplier < 1 || $multiplierLimited < 1) {
            $error = 'Kelipatan plafon minimal 1.';
        } elseif ($minSavings < 0) {
            $error = 'Minimal simpanan wajib tidak valid.';
        } else {
            $dbHelper->setSystemSetting('loan_interest_rate', $interestRate, 'number', 'Suku bunga pinjaman per bulan (%)', $user['id']);
            $dbHelper->setSystemSetting('savings_interest_rate', 0, 'number', 'Bunga simpanan sukarela dinonaktifkan (ditiadakan RAT ke-19)', $user['id']);
            $dbHelper->setSystemSetting('max_loan_term', $maxTerm, 'number', 'Maksimal jangka waktu pinjaman termasuk kebijakan khusus (bulan)', $user['id']);
            $dbHelper->setSystemSetting('max_loan_term_standard', $standardTerm, 'number', 'Maksimal tenor standar tanpa surat pernyataan (bulan)', $user['id']);
            $dbHelper->setSystemSetting('max_loan_multiplier', $multiplier, 'number', 'Plafon pinjaman standar (kelipatan simpanan wajib)', $user['id']);
            $dbHelper->setSystemSetting('max_loan_multiplier_limited', $multiplierLimited, 'number', 'Plafon pinjaman terbatas outsourcing / tanpa potongan HRD', $user['id']);
            $dbHelper->setSystemSetting('min_mandatory_savings', $minSavings, 'number', 'Minimal simpanan wajib anggota (Rp)', $user['id']);
            $dbHelper->setSystemSetting('min_savings_balance', $minSavings, 'number', 'Saldo minimal simpanan wajib (Rp)', $user['id']);
            $dbHelper->setSystemSetting('min_employment_months', $minEmployment, 'number', 'Minimal masa kerja untuk pinjaman (bulan)', $user['id']);
            $dbHelper->setSystemSetting('late_fee_percentage', $lateFee, 'number', 'Denda keterlambatan per hari (%)', $user['id']);
            $dbHelper->setSystemSetting('company_name', $companyName, 'string', 'Nama koperasi', $user['id']);
            $dbHelper->setSystemSetting('voluntary_savings_enabled', 0, 'boolean', 'Pelayanan Simpanan Sukarela (ditiadakan RAT ke-19)', $user['id']);
            $dbHelper->logActivity($user['id'], 'general_settings_updated', 'system_setting', null, null, ['interest' => $interestRate]);
            $success = 'Pengaturan umum berhasil disimpan sesuai ketentuan KOPPUJA.';
        }
    } elseif ($form === 'auto_approve') {
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
            $dbHelper->logActivity($user['id'], 'auto_approve_settings_updated', 'system_setting', null, null, ['enabled' => $enabled, 'max_amount' => $maxAmount, 'max_term' => $maxTerm]);
            $success = 'Pengaturan auto-approve berhasil disimpan.';
        }
    } elseif ($form === 'shu') {
        $shuPct = [
            'shu_pct_cadangan' => (float)($_POST['shu_pct_cadangan'] ?? 0),
            'shu_pct_jasa_anggota' => (float)($_POST['shu_pct_jasa_anggota'] ?? 0),
            'shu_pct_dana_pengurus' => (float)($_POST['shu_pct_dana_pengurus'] ?? 0),
            'shu_pct_dana_karyawan' => (float)($_POST['shu_pct_dana_karyawan'] ?? 0),
            'shu_pct_dana_pendidikan' => (float)($_POST['shu_pct_dana_pendidikan'] ?? 0),
            'shu_pct_dana_sosial' => (float)($_POST['shu_pct_dana_sosial'] ?? 0),
        ];
        $jasaSimpanan = (float)($_POST['shu_pct_jasa_simpanan'] ?? 0);
        $jasaPinjaman = (float)($_POST['shu_pct_jasa_pinjaman'] ?? 0);
        $mainTotal = array_sum($shuPct);

        if (min($shuPct) < 0 || $jasaSimpanan < 0 || $jasaPinjaman < 0) {
            $error = 'Persentase SHU tidak boleh negatif.';
        } elseif (abs($mainTotal - 100) > 0.05) {
            $error = 'Total alokasi SHU harus 100%. Saat ini ' . number_format($mainTotal, 1) . '%.';
        } elseif (abs($jasaSimpanan + $jasaPinjaman - 100) > 0.05) {
            $error = 'Porsi jasa simpanan + jasa pinjaman harus 100%.';
        } else {
            foreach ($shuPct as $key => $val) {
                $labels = [
                    'shu_pct_cadangan' => 'Alokasi SHU untuk cadangan koperasi (%)',
                    'shu_pct_jasa_anggota' => 'Alokasi SHU untuk jasa anggota (%)',
                    'shu_pct_dana_pengurus' => 'Alokasi SHU untuk dana pengurus (%)',
                    'shu_pct_dana_karyawan' => 'Alokasi SHU untuk dana karyawan (%)',
                    'shu_pct_dana_pendidikan' => 'Alokasi SHU untuk dana pendidikan (%)',
                    'shu_pct_dana_sosial' => 'Alokasi SHU untuk dana sosial (%)',
                ];
                $dbHelper->setSystemSetting($key, $val, 'number', $labels[$key], $user['id']);
            }
            $dbHelper->setSystemSetting('shu_pct_jasa_simpanan', $jasaSimpanan, 'number', 'Porsi jasa simpanan dari jasa anggota (%)', $user['id']);
            $dbHelper->setSystemSetting('shu_pct_jasa_pinjaman', $jasaPinjaman, 'number', 'Porsi jasa pinjaman dari jasa anggota (%)', $user['id']);
            $dbHelper->logActivity($user['id'], 'shu_settings_updated', 'system_setting', null, null, $shuPct);
            $success = 'Pengaturan alokasi SHU berhasil disimpan.';
        }
    }
}

function settingValue($dbHelper, $key, $default) {
    $val = $dbHelper->getSystemSetting($key);
    return ($val === null || $val === '') ? $default : $val;
}

$loanInterest = settingValue($dbHelper, 'loan_interest_rate', DEFAULT_LOAN_INTEREST_RATE);
$maxLoanTerm = settingValue($dbHelper, 'max_loan_term', MAX_LOAN_TERM_EXTENDED);
$standardTerm = settingValue($dbHelper, 'max_loan_term_standard', MAX_LOAN_TERM_STANDARD);
$multiplier = settingValue($dbHelper, 'max_loan_multiplier', MAX_LOAN_MULTIPLIER);
$multiplierLimited = settingValue($dbHelper, 'max_loan_multiplier_limited', MAX_LOAN_MULTIPLIER_LIMITED);
$minSavings = settingValue($dbHelper, 'min_mandatory_savings', MIN_MANDATORY_SAVINGS);
$minEmployment = settingValue($dbHelper, 'min_employment_months', MIN_EMPLOYMENT_MONTHS);
$lateFee = settingValue($dbHelper, 'late_fee_percentage', LATE_FEE_PERCENTAGE);
$companyName = settingValue($dbHelper, 'company_name', 'Koperasi Konsumen Karyawan PT. Puninar Jaya (KOPPUJA)');

$aaEnabled = $dbHelper->getSystemSetting('loan_auto_approve_enabled');
if ($aaEnabled === null) $aaEnabled = LOAN_AUTO_APPROVE_ENABLED;
$aaMaxAmount = settingValue($dbHelper, 'loan_auto_approve_max_amount', LOAN_AUTO_APPROVE_MAX_AMOUNT);
$aaMaxTerm = settingValue($dbHelper, 'loan_auto_approve_max_term_months', LOAN_AUTO_APPROVE_MAX_TERM_MONTHS);

$shuPctCadangan = settingValue($dbHelper, 'shu_pct_cadangan', 25);
$shuPctJasaAnggota = settingValue($dbHelper, 'shu_pct_jasa_anggota', 40);
$shuPctJasaSimpanan = settingValue($dbHelper, 'shu_pct_jasa_simpanan', 50);
$shuPctJasaPinjaman = settingValue($dbHelper, 'shu_pct_jasa_pinjaman', 50);
$shuPctDanaPengurus = settingValue($dbHelper, 'shu_pct_dana_pengurus', 10);
$shuPctDanaKaryawan = settingValue($dbHelper, 'shu_pct_dana_karyawan', 5);
$shuPctDanaPendidikan = settingValue($dbHelper, 'shu_pct_dana_pendidikan', 10);
$shuPctDanaSosial = settingValue($dbHelper, 'shu_pct_dana_sosial', 10);

renderAdminHeader('Pengaturan - Admin', 'admin-settings.php', $user, 'Pengaturan Sistem (KOPPUJA 2025)');
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="alert alert-info">
    <strong>Ketentuan Koperasi PT Puninar Jaya:</strong>
    Plafon pinjaman berbasis <em>simpanan wajib</em> (2x dengan potongan HRD, 1x outsourcing/angsur mandiri),
    tenor standar 12 bulan (kebijakan 18 bulan dengan surat pernyataan), simpanan wajib beku, simpanan sukarela ditiadakan.
</div>

<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card content-card">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-sliders-h text-primary me-2"></i> Pengaturan Umum</h5></div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                    <input type="hidden" name="form" value="general">
                    <div class="mb-3">
                        <label class="form-label">Nama Koperasi</label>
                        <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($companyName); ?>">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Bunga Pinjaman (% / bulan)</label>
                            <input type="number" step="0.01" name="loan_interest_rate" class="form-control" value="<?php echo htmlspecialchars($loanInterest); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Min. Masa Kerja (bulan)</label>
                            <input type="number" name="min_employment_months" class="form-control" value="<?php echo (int)$minEmployment; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Plafon Standar (x simpanan wajib)</label>
                            <input type="number" step="0.1" name="max_loan_multiplier" class="form-control" value="<?php echo htmlspecialchars($multiplier); ?>">
                            <div class="form-text">Untuk karyawan/driver dengan potongan HRD</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Plafon Terbatas (x simpanan wajib)</label>
                            <input type="number" step="0.1" name="max_loan_multiplier_limited" class="form-control" value="<?php echo htmlspecialchars($multiplierLimited); ?>">
                            <div class="form-text">Untuk outsourcing / tanpa potongan HRD</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tenor Standar (bulan)</label>
                            <input type="number" name="max_loan_term_standard" class="form-control" value="<?php echo (int)$standardTerm; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tenor Kebijakan Maks. (bulan)</label>
                            <input type="number" name="max_loan_term" class="form-control" value="<?php echo (int)$maxLoanTerm; ?>">
                            <div class="form-text">Butuh surat pernyataan bermaterai</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Minimal Simpanan Wajib (Rp)</label>
                            <input type="text" name="min_mandatory_savings" class="form-control" value="<?php echo number_format((float)$minSavings, 0, ',', '.'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Denda Telat (% / hari)</label>
                            <input type="number" step="0.01" name="late_fee_percentage" class="form-control" value="<?php echo htmlspecialchars($lateFee); ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Simpan</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5 mb-4">
        <div class="card content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-robot text-primary me-2"></i> Persetujuan Otomatis</h5>
                <span class="badge bg-<?php echo $aaEnabled ? 'success' : 'secondary'; ?>"><?php echo $aaEnabled ? 'Aktif' : 'Nonaktif'; ?></span>
            </div>
            <div class="card-body">
                <p class="text-muted small">Pengajuan yang memenuhi kriteria (dan lolos kelayakan) akan disetujui otomatis. Tenor &gt; standar serta anggota tanpa potongan HRD tetap masuk review manual.</p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                    <input type="hidden" name="form" value="auto_approve">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="aa_enabled" name="auto_approve_enabled" value="1" <?php echo $aaEnabled ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="aa_enabled">Aktifkan Auto-Approve</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Batas Maksimal Jumlah Pinjaman</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="text" name="auto_approve_max_amount" id="aa_amount" class="form-control" value="<?php echo number_format((float)$aaMaxAmount, 0, ',', '.'); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Batas Maksimal Tenor</label>
                        <div class="input-group">
                            <input type="number" name="auto_approve_max_term" class="form-control" min="1" max="18" value="<?php echo (int)$aaMaxTerm; ?>">
                            <span class="input-group-text">Bulan</span>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Simpan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card content-card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-coins text-primary me-2"></i> Alokasi Pembagian SHU</h5>
        <a href="admin-shu.php" class="btn btn-sm btn-outline-primary">Lihat Laporan SHU</a>
    </div>
    <div class="card-body">
        <p class="text-muted">Persentase utama harus berjumlah 100%. Porsi jasa simpanan dan jasa pinjaman adalah pecahan dari jasa anggota, juga harus 100%.</p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
            <input type="hidden" name="form" value="shu">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Cadangan (%)</label>
                    <input type="number" step="0.1" min="0" max="100" name="shu_pct_cadangan" class="form-control" value="<?php echo htmlspecialchars($shuPctCadangan); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Jasa Anggota (%)</label>
                    <input type="number" step="0.1" min="0" max="100" name="shu_pct_jasa_anggota" class="form-control" value="<?php echo htmlspecialchars($shuPctJasaAnggota); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Dana Pengurus (%)</label>
                    <input type="number" step="0.1" min="0" max="100" name="shu_pct_dana_pengurus" class="form-control" value="<?php echo htmlspecialchars($shuPctDanaPengurus); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Dana Karyawan (%)</label>
                    <input type="number" step="0.1" min="0" max="100" name="shu_pct_dana_karyawan" class="form-control" value="<?php echo htmlspecialchars($shuPctDanaKaryawan); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Dana Pendidikan (%)</label>
                    <input type="number" step="0.1" min="0" max="100" name="shu_pct_dana_pendidikan" class="form-control" value="<?php echo htmlspecialchars($shuPctDanaPendidikan); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Dana Sosial (%)</label>
                    <input type="number" step="0.1" min="0" max="100" name="shu_pct_dana_sosial" class="form-control" value="<?php echo htmlspecialchars($shuPctDanaSosial); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Porsi Jasa Simpanan (% dari jasa anggota)</label>
                    <input type="number" step="0.1" min="0" max="100" name="shu_pct_jasa_simpanan" class="form-control" value="<?php echo htmlspecialchars($shuPctJasaSimpanan); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Porsi Jasa Pinjaman (% dari jasa anggota)</label>
                    <input type="number" step="0.1" min="0" max="100" name="shu_pct_jasa_pinjaman" class="form-control" value="<?php echo htmlspecialchars($shuPctJasaPinjaman); ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Simpan Alokasi SHU</button>
        </form>
    </div>
</div>

<script>
    document.querySelectorAll('input[name="min_mandatory_savings"], #aa_amount').forEach(function(el) {
        el.addEventListener('input', function(e) {
            let v = e.target.value.replace(/[^0-9]/g, '');
            e.target.value = v ? new Intl.NumberFormat('id-ID').format(parseInt(v)) : '';
        });
    });
</script>

<?php renderAdminFooter(); ?>
