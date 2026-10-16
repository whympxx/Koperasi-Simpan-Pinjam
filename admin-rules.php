<?php
/**
 * Admin - Basis Aturan (Rule-Based IF-THEN)
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

require_once 'includes/init.php';

$auth->requireAuth();
$auth->requireRole(['admin', 'super_admin']);

$user = $auth->getCurrentUser();
$engine = new RuleEngine();

$error = '';
$success = '';
$editingRule = null;
$simulation = null;
$simulationInput = [];

$factCatalog = RuleEngine::factCatalog();
$operators = RuleEngine::operatorLabels();
$groupLabels = RuleEngine::groupLabels();
$conclusionLabels = RuleEngine::conclusionLabels();

$statusBadges = [
    RuleEngine::STATUS_FIRED     => ['success', 'Terpenuhi'],
    RuleEngine::STATUS_NOT_FIRED => ['secondary', 'Tidak terpenuhi'],
    RuleEngine::STATUS_SKIPPED   => ['light text-dark', 'Dilewati'],
];

$conclusionBadges = [
    'approve'       => 'success',
    'reject'        => 'danger',
    'manual_review' => 'warning',
];

if ($_POST) {
    $action = $_POST['action'] ?? '';

    if (!$auth->verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid.';
    } elseif ($action === 'save_rule') {
        $data = [
            'id'          => (int)($_POST['rule_id'] ?? 0),
            'rule_code'   => strtoupper(trim(SecurityHelper::sanitize($_POST['rule_code'] ?? ''))),
            'rule_group'  => $_POST['rule_group'] ?? '',
            'name'        => SecurityHelper::sanitize($_POST['name'] ?? ''),
            'description' => SecurityHelper::sanitize($_POST['description'] ?? ''),
            'conclusion'  => $_POST['conclusion'] ?? '',
            'message'     => SecurityHelper::sanitize($_POST['message'] ?? ''),
            'priority'    => (int)($_POST['priority'] ?? 100),
            'is_active'   => !empty($_POST['is_active']),
        ];

        $conditions = [];
        $factKeys = $_POST['fact_key'] ?? [];
        foreach ($factKeys as $index => $factKey) {
            if ($factKey === '') {
                continue;
            }

            $conditions[] = [
                'fact_key'       => $factKey,
                'operator'       => $_POST['operator'][$index] ?? '=',
                'value_type'     => $_POST['value_type'][$index] ?? 'static',
                'compare_value'  => trim(SecurityHelper::sanitize($_POST['compare_value'][$index] ?? '')),
                'fallback_value' => trim(SecurityHelper::sanitize($_POST['fallback_value'][$index] ?? '')),
            ];
        }

        $result = $engine->saveRule($data, $conditions, $user['id']);

        if ($result['success']) {
            $dbHelper->logActivity($user['id'], 'rule_saved', 'rule', $result['rule_id'] ?? null, null, [
                'rule_code' => $data['rule_code'],
                'group' => $data['rule_group']
            ]);
            $success = $result['message'];
        } else {
            $error = $result['message'];
            $editingRule = array_merge($data, ['conditions' => $conditions]);
        }
    } elseif ($action === 'toggle_rule') {
        $ruleId = (int)($_POST['rule_id'] ?? 0);
        $isActive = !empty($_POST['is_active']);
        $engine->setActive($ruleId, $isActive, $user['id']);
        $dbHelper->logActivity($user['id'], 'rule_status_changed', 'rule', $ruleId, null, ['is_active' => $isActive]);
        $success = $isActive ? 'Aturan diaktifkan.' : 'Aturan dinonaktifkan.';
    } elseif ($action === 'delete_rule') {
        $ruleId = (int)($_POST['rule_id'] ?? 0);
        $result = $engine->deleteRule($ruleId);

        if ($result['success']) {
            $dbHelper->logActivity($user['id'], 'rule_deleted', 'rule', $ruleId);
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'simulate') {
        $simulationInput = [
            'masa_kerja_bulan'        => (int)($_POST['sim_masa_kerja'] ?? 0),
            'status_karyawan'         => $_POST['sim_status'] ?? 'active',
            'kategori_anggota'        => $_POST['sim_kategori'] ?? 'karyawan',
            'potongan_hrd'            => (int)($_POST['sim_potongan_hrd'] ?? 1),
            'gaji_bulanan'            => (float)str_replace(['.', ','], ['', '.'], $_POST['sim_gaji'] ?? '0'),
            'simpanan_wajib'          => (float)str_replace(['.', ','], ['', '.'], $_POST['sim_simpanan'] ?? '0'),
            'punya_pinjaman_aktif'    => (int)($_POST['sim_pinjaman_aktif'] ?? 0),
            'punya_pengajuan_pending' => (int)($_POST['sim_pengajuan_pending'] ?? 0),
            'riwayat_tunggakan'       => (int)($_POST['sim_tunggakan'] ?? 0),
            'pinjaman_lunas'          => (int)($_POST['sim_lunas'] ?? 0),
            'jumlah_pinjaman'         => (float)str_replace(['.', ','], ['', '.'], $_POST['sim_jumlah'] ?? '0'),
            'tenor_bulan'             => (int)($_POST['sim_tenor'] ?? 0),
        ];

        $interestRate = $dbHelper->getSystemSetting('loan_interest_rate') ?: DEFAULT_LOAN_INTEREST_RATE;
        $autoApproveEnabled = $dbHelper->getSystemSetting('loan_auto_approve_enabled');
        if ($autoApproveEnabled === null) {
            $autoApproveEnabled = LOAN_AUTO_APPROVE_ENABLED;
        }
        if (isset($_POST['sim_auto_approve']) && $_POST['sim_auto_approve'] !== '') {
            $autoApproveEnabled = (int)$_POST['sim_auto_approve'] === 1;
        }
        $simulationInput['auto_approve_aktif'] = $autoApproveEnabled ? 1 : 0;

        $fakeUser = [
            'member_category' => $simulationInput['kategori_anggota'],
            'payroll_deduction' => $simulationInput['potongan_hrd'],
        ];
        $batasKelipatan = getLoanMultiplierForUser($fakeUser, $dbHelper);
        $plafon = floor($simulationInput['simpanan_wajib'] * $batasKelipatan);

        $facts = $simulationInput;
        $facts['auto_approve_aktif'] = $autoApproveEnabled ? 1 : 0;
        $facts['batas_kelipatan'] = $batasKelipatan;
        $facts['plafon_maksimal'] = $plafon;
        $facts['potongan_hrd'] = $simulationInput['potongan_hrd'];

        if ($facts['jumlah_pinjaman'] > 0 && $facts['tenor_bulan'] > 0) {
            $installment = calculateInstallment($facts['jumlah_pinjaman'], $interestRate, $facts['tenor_bulan']);
            $facts['angsuran_bulanan'] = round($installment, 2);
            $facts['kelipatan_simpanan'] = $facts['simpanan_wajib'] > 0
                ? round($facts['jumlah_pinjaman'] / $facts['simpanan_wajib'], 2) : 999;
            $facts['kelipatan_gaji'] = $facts['gaji_bulanan'] > 0
                ? round($facts['jumlah_pinjaman'] / $facts['gaji_bulanan'], 2) : 0;
            $facts['rasio_angsuran_gaji'] = $facts['gaji_bulanan'] > 0
                ? round($installment / $facts['gaji_bulanan'] * 100, 2) : 0;
        }

        $eligibility = $engine->evaluate(
            RuleEngine::GROUP_ELIGIBILITY,
            $facts,
            'eligible',
            'Memenuhi seluruh syarat pengajuan.'
        );

        $simulation = [
            'facts' => $facts,
            'eligibility' => $eligibility,
            'decision' => null,
        ];

        if ($eligibility['decision'] !== 'reject') {
            $simulation['decision'] = $engine->evaluate(
                RuleEngine::GROUP_DECISION,
                $facts,
                'manual_review',
                'Tidak ada aturan persetujuan otomatis yang terpenuhi.'
            );
        }
    }
}

if (!$editingRule && isset($_GET['edit'])) {
    $editingRule = $engine->getRule((int)$_GET['edit']);
}

$isNewRule = empty($editingRule['id']);
$formRule = $editingRule ?: [
    'id' => 0,
    'rule_code' => $engine->isReady() ? $engine->generateRuleCode() : 'R01',
    'rule_group' => RuleEngine::GROUP_ELIGIBILITY,
    'name' => '',
    'description' => '',
    'conclusion' => 'reject',
    'message' => '',
    'priority' => 100,
    'is_active' => 1,
    'conditions' => [],
];

if (empty($formRule['conditions'])) {
    $formRule['conditions'] = [[
        'fact_key' => 'masa_kerja_bulan',
        'operator' => '<',
        'value_type' => 'static',
        'compare_value' => '',
        'fallback_value' => '',
    ]];
}

$allRules = $engine->getRules();
$rulesByGroup = [];
foreach ($allRules as $rule) {
    $rulesByGroup[$rule['rule_group']][] = $rule;
}

$evaluationLogs = $engine->getEvaluationLogs(10);

renderAdminHeader('Basis Aturan - Admin', 'admin-rules.php', $user, 'Basis Aturan (Rule-Based IF-THEN)');
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if (!$engine->isReady()): ?>
    <div class="alert alert-warning">
        <h5 class="alert-heading"><i class="fas fa-database me-2"></i> Basis aturan belum dipasang</h5>
        <p class="mb-2">Tabel <code>rules</code> dan <code>rule_conditions</code> belum ada di database. Sistem sementara memakai aturan bawaan yang tertanam di kode.</p>
        <p class="mb-0 small">Jalankan migrasi berikut untuk mengaktifkan basis aturan:
            <code>mysql -u root simpanpinjam_puninar &lt; database/add_rule_engine_tables.sql</code>
        </p>
    </div>
<?php endif; ?>

<div class="card content-card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-info-circle text-primary me-2"></i> Cara kerja</h5>
    </div>
    <div class="card-body">
        <p class="mb-2">Setiap aturan berbentuk <strong>JIKA (seluruh kondisi terpenuhi) MAKA (kesimpulan)</strong>. Mesin inferensi mengevaluasi aturan berurutan dari prioritas terkecil, dan aturan pertama yang terpenuhi menentukan keputusan.</p>
        <ul class="mb-0 small text-muted">
            <li><strong>Kelayakan Pengajuan</strong> dievaluasi lebih dulu. Bila ada aturan bernilai <em>Tolak</em> yang terpenuhi, pengajuan langsung ditolak.</li>
            <li><strong>Keputusan Persetujuan</strong> hanya dijalankan bila nasabah lolos kelayakan, untuk menentukan disetujui otomatis atau direview admin.</li>
            <li>Nilai pembanding dapat diambil dari <em>Pengaturan Sistem</em> agar aturan ikut menyesuaikan ketika parameter diubah.</li>
            <li>Gunakan <code>{nilai}</code> dan <code>{fakta}</code> pada pesan untuk menampilkan nilai pembanding dan nilai fakta nasabah.</li>
        </ul>
    </div>
</div>

<?php foreach ($groupLabels as $groupKey => $groupLabel): ?>
    <div class="card content-card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-project-diagram text-primary me-2"></i> <?php echo $groupLabel; ?></h5>
            <span class="badge bg-secondary"><?php echo count($rulesByGroup[$groupKey] ?? []); ?> aturan</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;">Kode</th>
                            <th style="width: 70px;">Prio.</th>
                            <th>JIKA</th>
                            <th>MAKA</th>
                            <th style="width: 110px;">Status</th>
                            <th style="width: 150px;" class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rulesByGroup[$groupKey])): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada aturan pada kelompok ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rulesByGroup[$groupKey] as $rule): ?>
                                <tr class="<?php echo $rule['is_active'] ? '' : 'opacity-50'; ?>">
                                    <td><span class="badge bg-dark"><?php echo htmlspecialchars($rule['rule_code']); ?></span></td>
                                    <td><?php echo (int)$rule['priority']; ?></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($rule['name']); ?></div>
                                        <code class="small"><?php echo htmlspecialchars($engine->describeRuleConditions($rule)); ?></code>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $conclusionBadges[$rule['conclusion']] ?? 'secondary'; ?>">
                                            <?php echo $conclusionLabels[$rule['conclusion']] ?? $rule['conclusion']; ?>
                                        </span>
                                        <div class="small text-muted mt-1"><?php echo htmlspecialchars($rule['message']); ?></div>
                                    </td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="toggle_rule">
                                            <input type="hidden" name="rule_id" value="<?php echo (int)$rule['id']; ?>">
                                            <input type="hidden" name="is_active" value="<?php echo $rule['is_active'] ? '0' : '1'; ?>">
                                            <button type="submit" class="btn btn-sm btn-<?php echo $rule['is_active'] ? 'success' : 'outline-secondary'; ?>">
                                                <?php echo $rule['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-end">
                                        <a href="admin-rules.php?edit=<?php echo (int)$rule['id']; ?>#form-aturan" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if (!$rule['is_system']): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Hapus aturan <?php echo htmlspecialchars($rule['rule_code']); ?>?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="delete_rule">
                                                <input type="hidden" name="rule_id" value="<?php echo (int)$rule['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-outline-secondary" disabled title="Aturan inti sistem"><i class="fas fa-lock"></i></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<div class="row">
    <div class="col-lg-7 mb-4" id="form-aturan">
        <div class="card content-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-<?php echo $isNewRule ? 'plus' : 'edit'; ?> text-primary me-2"></i>
                    <?php echo $isNewRule ? 'Tambah Aturan Baru' : 'Ubah Aturan ' . htmlspecialchars($formRule['rule_code']); ?>
                </h5>
                <?php if (!$isNewRule): ?>
                    <a href="admin-rules.php" class="btn btn-sm btn-outline-secondary">Batal</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="save_rule">
                    <input type="hidden" name="rule_id" value="<?php echo (int)($formRule['id'] ?? 0); ?>">

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Kode</label>
                            <input type="text" name="rule_code" class="form-control" required
                                   value="<?php echo htmlspecialchars($formRule['rule_code']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kelompok Aturan</label>
                            <select name="rule_group" class="form-select">
                                <?php foreach ($groupLabels as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $formRule['rule_group'] === $key ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Prioritas</label>
                            <input type="number" name="priority" class="form-control" min="1" required
                                   value="<?php echo (int)$formRule['priority']; ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nama Aturan</label>
                        <input type="text" name="name" class="form-control" required
                               value="<?php echo htmlspecialchars($formRule['name']); ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($formRule['description'] ?? ''); ?></textarea>
                    </div>

                    <label class="form-label fw-semibold">JIKA (seluruh kondisi terpenuhi)</label>
                    <div id="conditionList" class="mb-2">
                        <?php foreach ($formRule['conditions'] as $index => $condition): ?>
                            <div class="condition-row border rounded p-2 mb-2 bg-light">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label small mb-1">Fakta</label>
                                        <select name="fact_key[]" class="form-select form-select-sm">
                                            <?php foreach ($factCatalog as $key => $meta): ?>
                                                <option value="<?php echo $key; ?>" <?php echo $condition['fact_key'] === $key ? 'selected' : ''; ?>>
                                                    <?php echo $meta['label']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1">Operator</label>
                                        <select name="operator[]" class="form-select form-select-sm">
                                            <?php foreach ($operators as $op => $label): ?>
                                                <option value="<?php echo htmlspecialchars($op); ?>" <?php echo $condition['operator'] === $op ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($op); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1">Sumber Nilai</label>
                                        <select name="value_type[]" class="form-select form-select-sm">
                                            <option value="static" <?php echo ($condition['value_type'] ?? 'static') === 'static' ? 'selected' : ''; ?>>Nilai tetap</option>
                                            <option value="setting" <?php echo ($condition['value_type'] ?? '') === 'setting' ? 'selected' : ''; ?>>Pengaturan sistem</option>
                                            <option value="fact" <?php echo ($condition['value_type'] ?? '') === 'fact' ? 'selected' : ''; ?>>Fakta lain</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1">Nilai</label>
                                        <input type="text" name="compare_value[]" class="form-control form-control-sm"
                                               value="<?php echo htmlspecialchars($condition['compare_value']); ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1">Cadangan</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="fallback_value[]" class="form-control form-control-sm"
                                                   value="<?php echo htmlspecialchars($condition['fallback_value'] ?? ''); ?>">
                                            <button type="button" class="btn btn-outline-danger btn-remove-condition"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="btnAddCondition">
                        <i class="fas fa-plus me-1"></i> Tambah Kondisi
                    </button>

                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label class="form-label fw-semibold">MAKA (kesimpulan)</label>
                            <select name="conclusion" class="form-select">
                                <?php foreach ($conclusionLabels as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $formRule['conclusion'] === $key ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-7 mb-3">
                            <label class="form-label fw-semibold">Pesan Hasil</label>
                            <input type="text" name="message" class="form-control" required
                                   value="<?php echo htmlspecialchars($formRule['message']); ?>">
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                               <?php echo !empty($formRule['is_active']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">Aturan aktif</label>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Simpan Aturan</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5 mb-4">
        <div class="card content-card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-flask text-primary me-2"></i> Simulasi Aturan</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">Uji kombinasi fakta terhadap basis aturan tanpa membuat pengajuan sungguhan. Pilih kasus BVA (TC-15 s.d. TC-26) untuk mengisi nilai batas secara otomatis.</p>
                <form method="POST" id="simulasiForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="simulate">
                    <div class="mb-2">
                        <label class="form-label small mb-1">Kasus uji nilai batas</label>
                        <select id="bvaPreset" class="form-select form-select-sm">
                            <option value="">— Pilih TC-15 s.d. TC-26 —</option>
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small mb-1">Masa kerja (bulan)</label>
                            <input type="number" name="sim_masa_kerja" class="form-control form-control-sm" value="<?php echo (int)($simulationInput['masa_kerja_bulan'] ?? 24); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Status karyawan</label>
                            <select name="sim_status" class="form-select form-select-sm">
                                <?php foreach (['active' => 'Aktif', 'inactive' => 'Nonaktif', 'resigned' => 'Resign'] as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($simulationInput['status_karyawan'] ?? 'active') === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Kategori anggota</label>
                            <select name="sim_kategori" class="form-select form-select-sm">
                                <?php foreach (['karyawan' => 'Karyawan', 'driver' => 'Driver', 'outsourcing' => 'Outsourcing'] as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($simulationInput['kategori_anggota'] ?? 'karyawan') === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Potongan HRD</label>
                            <select name="sim_potongan_hrd" class="form-select form-select-sm">
                                <option value="1" <?php echo ($simulationInput['potongan_hrd'] ?? 1) == 1 ? 'selected' : ''; ?>>Ya</option>
                                <option value="0" <?php echo isset($simulationInput['potongan_hrd']) && (int)$simulationInput['potongan_hrd'] === 0 ? 'selected' : ''; ?>>Tidak</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Simpanan wajib</label>
                            <input type="number" name="sim_simpanan" class="form-control form-control-sm" value="<?php echo (int)($simulationInput['simpanan_wajib'] ?? 2000000); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Gaji/komisi bulanan</label>
                            <input type="number" name="sim_gaji" class="form-control form-control-sm" value="<?php echo (int)($simulationInput['gaji_bulanan'] ?? 5000000); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Jumlah pinjaman</label>
                            <input type="number" name="sim_jumlah" class="form-control form-control-sm" value="<?php echo (int)($simulationInput['jumlah_pinjaman'] ?? 4000000); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Tenor (bulan)</label>
                            <input type="number" name="sim_tenor" class="form-control form-control-sm" value="<?php echo (int)($simulationInput['tenor_bulan'] ?? 12); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Riwayat tunggakan</label>
                            <input type="number" name="sim_tunggakan" class="form-control form-control-sm" value="<?php echo (int)($simulationInput['riwayat_tunggakan'] ?? 0); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Auto-approve aktif</label>
                            <select name="sim_auto_approve" class="form-select form-select-sm">
                                <option value="1" <?php echo (int)($simulationInput['auto_approve_aktif'] ?? 1) === 1 ? 'selected' : ''; ?>>Ya (1)</option>
                                <option value="0" <?php echo isset($simulationInput['auto_approve_aktif']) && (int)$simulationInput['auto_approve_aktif'] === 0 ? 'selected' : ''; ?>>Tidak (0)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Punya pinjaman aktif</label>
                            <select name="sim_pinjaman_aktif" class="form-select form-select-sm">
                                <option value="0" <?php echo empty($simulationInput['punya_pinjaman_aktif']) ? 'selected' : ''; ?>>Tidak</option>
                                <option value="1" <?php echo !empty($simulationInput['punya_pinjaman_aktif']) ? 'selected' : ''; ?>>Ya</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small mb-1">Pengajuan diproses</label>
                            <select name="sim_pengajuan_pending" class="form-select form-select-sm">
                                <option value="0" <?php echo empty($simulationInput['punya_pengajuan_pending']) ? 'selected' : ''; ?>>Tidak</option>
                                <option value="1" <?php echo !empty($simulationInput['punya_pengajuan_pending']) ? 'selected' : ''; ?>>Ya</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm mt-3"><i class="fas fa-play me-1"></i> Jalankan Simulasi</button>
                </form>
            </div>
        </div>

        <?php if ($simulation): ?>
            <?php
            $finalDecision = $simulation['eligibility']['decision'] === 'reject'
                ? 'reject'
                : ($simulation['decision']['decision'] ?? 'manual_review');
            $finalMessage = $simulation['eligibility']['decision'] === 'reject'
                ? $simulation['eligibility']['message']
                : ($simulation['decision']['message'] ?? '');
            $finalRule = $simulation['eligibility']['decision'] === 'reject'
                ? $simulation['eligibility']['rule_code']
                : ($simulation['decision']['rule_code'] ?? null);
            ?>
            <div class="card content-card">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-clipboard-check text-primary me-2"></i> Hasil Inferensi</h5></div>
                <div class="card-body">
                    <div class="alert alert-<?php echo $conclusionBadges[$finalDecision] ?? 'secondary'; ?> mb-3">
                        <strong><?php echo $conclusionLabels[$finalDecision] ?? $finalDecision; ?></strong>
                        <?php if ($finalRule): ?>
                            <span class="badge bg-dark ms-1"><?php echo htmlspecialchars($finalRule); ?></span>
                        <?php endif; ?>
                        <div class="small mt-1"><?php echo htmlspecialchars($finalMessage); ?></div>
                    </div>

                    <?php foreach (['eligibility' => 'Kelayakan', 'decision' => 'Keputusan'] as $stage => $stageLabel): ?>
                        <?php if (empty($simulation[$stage])) { continue; } ?>
                        <h6 class="fw-semibold mt-3"><?php echo $stageLabel; ?></h6>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <?php foreach ($simulation[$stage]['trace'] as $line): ?>
                                        <?php $badge = $statusBadges[$line['status']]; ?>
                                        <tr>
                                            <td style="width: 60px;"><span class="badge bg-dark"><?php echo htmlspecialchars($line['rule_code']); ?></span></td>
                                            <td>
                                                <div class="small"><?php echo htmlspecialchars($line['if_text']); ?></div>
                                                <?php foreach ($line['conditions'] as $condition): ?>
                                                    <div class="small text-muted">
                                                        <?php echo htmlspecialchars($condition['fact_label']); ?>:
                                                        nilai nasabah = <strong><?php echo htmlspecialchars((string)($condition['actual'] ?? '-')); ?></strong>,
                                                        pembanding = <strong><?php echo htmlspecialchars((string)($condition['expected'] ?? '-')); ?></strong>
                                                        <?php echo $condition['note'] ? '(' . htmlspecialchars($condition['note']) . ')' : ''; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </td>
                                            <td style="width: 110px;" class="text-end">
                                                <span class="badge bg-<?php echo $badge[0]; ?>"><?php echo $badge[1]; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card content-card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-history text-primary me-2"></i> Jejak Penalaran Terakhir</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Waktu</th>
                        <th>Nasabah</th>
                        <th>Kelompok</th>
                        <th>Aturan Pemicu</th>
                        <th>Keputusan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($evaluationLogs)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada keputusan yang tercatat.</td></tr>
                    <?php else: ?>
                        <?php foreach ($evaluationLogs as $log): ?>
                            <tr>
                                <td class="small"><?php echo formatDateIndo($log['created_at'], true); ?></td>
                                <td class="small"><?php echo htmlspecialchars($log['full_name'] ?? '-'); ?></td>
                                <td class="small"><?php echo $groupLabels[$log['rule_group']] ?? $log['rule_group']; ?></td>
                                <td><span class="badge bg-dark"><?php echo htmlspecialchars($log['fired_rule_code'] ?? '-'); ?></span></td>
                                <td>
                                    <span class="badge bg-<?php echo $conclusionBadges[$log['decision']] ?? 'secondary'; ?>">
                                        <?php echo $conclusionLabels[$log['decision']] ?? $log['decision']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    const bvaPresets = [
        { id: 'TC-15a', label: 'TC-15a — Masa kerja 12 bulan (lolos R01)', masa: 12, status: 'active', kategori: 'karyawan', potongan: 1, simpanan: 2000000, gaji: 5000000, jumlah: 1000000, tenor: 12, tunggakan: 0, auto: 1 },
        { id: 'TC-15b', label: 'TC-15b — Masa kerja 11 bulan (tolak R01)', masa: 11, status: 'active', kategori: 'karyawan', potongan: 1, simpanan: 2000000, gaji: 5000000, jumlah: 1000000, tenor: 12, tunggakan: 0, auto: 1 },
        { id: 'TC-16a', label: 'TC-16a — Simpanan wajib Rp50.000 (lolos R08)', masa: 24, status: 'active', kategori: 'karyawan', potongan: 1, simpanan: 50000, gaji: 5000000, jumlah: 100000, tenor: 12, tunggakan: 0, auto: 1 },
        { id: 'TC-16b', label: 'TC-16b — Simpanan wajib Rp49.999 (tolak R08)', masa: 24, status: 'active', kategori: 'karyawan', potongan: 1, simpanan: 49999, gaji: 5000000, jumlah: 100000, tenor: 12, tunggakan: 0, auto: 1 },
        { id: 'TC-17a', label: 'TC-17a — Plafon 2× Rp4.000.000 (diterima)', masa: 24, status: 'active', kategori: 'karyawan', potongan: 1, simpanan: 2000000, gaji: 5000000, jumlah: 4000000, tenor: 12, tunggakan: 0, auto: 1 },
        { id: 'TC-17b', label: 'TC-17b — Plafon 2× Rp4.000.001 (tolak R05)', masa: 24, status: 'active', kategori: 'karyawan', potongan: 1, simpanan: 2000000, gaji: 5000000, jumlah: 4000001, tenor: 12, tunggakan: 0, auto: 1 },
        { id: 'TC-18a', label: 'TC-18a — Plafon 1× Rp2.000.000 (diterima)', masa: 24, status: 'active', kategori: 'outsourcing', potongan: 0, simpanan: 2000000, gaji: 5000000, jumlah: 2000000, tenor: 12, tunggakan: 0, auto: 1 },
        { id: 'TC-18b', label: 'TC-18b — Plafon 1× Rp2.000.001 (tolak R05)', masa: 24, status: 'active', kategori: 'outsourcing', potongan: 0, simpanan: 2000000, gaji: 5000000, jumlah: 2000001, tenor: 12, tunggakan: 0, auto: 1 },
        { id: 'TC-19a', label: 'TC-19a — Tenor 18 bulan (lolos R06)', masa: 24, status: 'active', kategori: 'karyawan', potongan: 1, simpanan: 2000000, gaji: 5000000, jumlah: 1000000, tenor: 18, tunggakan: 0, auto: 1 },
        { id: 'TC-19b', label: 'TC-19b — Tenor 19 bulan (tolak R06)', masa: 24, status: 'active', kategori: 'karyawan', potongan: 1, simpanan: 2000000, gaji: 5000000, jumlah: 1000000, tenor: 19, tunggakan: 0, auto: 1 },
        { id: 'TC-20a', label: 'TC-20a — Rasio angsuran 40% (R07 tidak terpicu)', masa: 24, status: 'active', kategori: 'karyawan', potongan: 1, simpanan: 5000000, gaji: 1000000, jumlah: 3478300, tenor: 12, tunggakan: 0, auto: 1 },
        { id: 'TC-20b', label: 'TC-20b — Rasio angsuran 41% (R07 bila diaktifkan)', masa: 24, status: 'active', kategori: 'karyawan', potongan: 1, simpanan: 5000000, gaji: 1000000, jumlah: 3565300, tenor: 12, tunggakan: 0, auto: 1 },
        { id: 'TC-21a', label: 'TC-21a — Nominal auto-approve Rp5.000.000', masa: 24, status: 'active', kategori: 'karyawan', potongan: 1, simpanan: 3000000, gaji: 8000000, jumlah: 5000000, tenor: 12, tunggakan: 0, auto: 1 },
        { id: 'TC-21b', label: 'TC-21b — Nominal auto-approve Rp5.000.001', masa: 24, status: 'active', kategori: 'karyawan', potongan: 1, simpanan: 3000000, gaji: 8000000, jumlah: 5000001, tenor: 12, tunggakan: 0, auto: 1 },
        { id: 'TC-22a', label: 'TC-22a — Tenor auto-approve 12 bulan', masa: 24, status: 'active', kategori: 'karyawan', potongan: 1, simpanan: 2000000, gaji: 5000000, jumlah: 1000000, tenor: 12, tunggakan: 0, auto: 1 },
        { id: 'TC-22b', label: 'TC-22b — Tenor auto-approve 13 bulan', masa: 24, status: 'active', kategori: 'karyawan', potongan: 1, simpanan: 2000000, gaji: 5000000, jumlah: 1000000, tenor: 13, tunggakan: 0, auto: 1 },
        { id: 'TC-23a', label: 'TC-23a — Tunggakan 2 kali (tidak memicu R13)', masa: 24, status: 'active', kategori: 'karyawan', potongan: 1, simpanan: 2000000, gaji: 5000000, jumlah: 1000000, tenor: 12, tunggakan: 2, auto: 1 },
        { id: 'TC-23b', label: 'TC-23b — Tunggakan 3 kali (review R13)', masa: 24, status: 'active', kategori: 'karyawan', potongan: 1, simpanan: 2000000, gaji: 5000000, jumlah: 1000000, tenor: 12, tunggakan: 3, auto: 1 },
        { id: 'TC-26a', label: 'TC-26a — Auto-approve 1 & potongan HRD 1', masa: 24, status: 'active', kategori: 'karyawan', potongan: 1, simpanan: 2000000, gaji: 5000000, jumlah: 1000000, tenor: 12, tunggakan: 0, auto: 1 },
        { id: 'TC-26b', label: 'TC-26b — Auto-approve 0 (R10 review)', masa: 24, status: 'active', kategori: 'karyawan', potongan: 1, simpanan: 2000000, gaji: 5000000, jumlah: 1000000, tenor: 12, tunggakan: 0, auto: 0 },
        { id: 'TC-26c', label: 'TC-26c — Potongan HRD 0 (R16 review)', masa: 24, status: 'active', kategori: 'outsourcing', potongan: 0, simpanan: 2000000, gaji: 5000000, jumlah: 1000000, tenor: 12, tunggakan: 0, auto: 1 }
    ];

    (function initBvaPresets() {
        const select = document.getElementById('bvaPreset');
        if (!select) {
            return;
        }
        bvaPresets.forEach(function(item) {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.label;
            select.appendChild(option);
        });
        select.addEventListener('change', function() {
            const preset = bvaPresets.find(function(item) { return item.id === this.value; }.bind(this));
            if (!preset) {
                return;
            }
            const form = document.getElementById('simulasiForm');
            form.querySelector('[name="sim_masa_kerja"]').value = preset.masa;
            form.querySelector('[name="sim_status"]').value = preset.status;
            form.querySelector('[name="sim_kategori"]').value = preset.kategori;
            form.querySelector('[name="sim_potongan_hrd"]').value = String(preset.potongan);
            form.querySelector('[name="sim_simpanan"]').value = preset.simpanan;
            form.querySelector('[name="sim_gaji"]').value = preset.gaji;
            form.querySelector('[name="sim_jumlah"]').value = preset.jumlah;
            form.querySelector('[name="sim_tenor"]').value = preset.tenor;
            form.querySelector('[name="sim_tunggakan"]').value = preset.tunggakan;
            form.querySelector('[name="sim_auto_approve"]').value = String(preset.auto);
            form.querySelector('[name="sim_pinjaman_aktif"]').value = '0';
            form.querySelector('[name="sim_pengajuan_pending"]').value = '0';
        });
    })();

    document.getElementById('btnAddCondition').addEventListener('click', function() {
        const list = document.getElementById('conditionList');
        const template = list.querySelector('.condition-row');
        const row = template.cloneNode(true);

        row.querySelectorAll('input').forEach(function(input) { input.value = ''; });
        row.querySelectorAll('select').forEach(function(select) { select.selectedIndex = 0; });
        list.appendChild(row);
    });

    document.getElementById('conditionList').addEventListener('click', function(event) {
        const button = event.target.closest('.btn-remove-condition');
        if (!button) {
            return;
        }

        const rows = this.querySelectorAll('.condition-row');
        if (rows.length <= 1) {
            return;
        }

        button.closest('.condition-row').remove();
    });
</script>

<?php renderAdminFooter(); ?>
