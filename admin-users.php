<?php
/**
 * Admin - Manajemen User
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

require_once 'includes/init.php';

$auth->requireAuth();
$auth->requireRole(['admin', 'super_admin']);

$user = $auth->getCurrentUser();

$error = '';
$success = '';
$showRegisterForm = isset($_GET['register']);
$formData = [
    'nik' => '',
    'full_name' => '',
    'email' => '',
    'phone' => '',
    'division_id' => '',
    'position' => '',
    'member_category' => 'karyawan',
    'payroll_deduction' => '1',
    'employment_date' => '',
    'monthly_salary' => '',
    'address' => '',
];

$divisions = $db->select("SELECT id, name, code FROM divisions ORDER BY name");

// Handle status toggle / membership rules / register nasabah
if ($_POST) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($action === 'register_nasabah') {
        $showRegisterForm = true;
    }

    if (!$auth->verifyCSRFToken($csrfToken)) {
        $error = 'Token keamanan tidak valid.';
    } elseif ($action === 'register_nasabah') {
        $formData = [
            'nik' => strtoupper(trim((string)($_POST['nik'] ?? ''))),
            'full_name' => trim((string)($_POST['full_name'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
            'phone' => trim((string)($_POST['phone'] ?? '')),
            'division_id' => (string)($_POST['division_id'] ?? ''),
            'position' => trim((string)($_POST['position'] ?? '')),
            'member_category' => $_POST['member_category'] ?? 'karyawan',
            'payroll_deduction' => !empty($_POST['payroll_deduction']) ? '1' : '0',
            'employment_date' => trim((string)($_POST['employment_date'] ?? '')),
            'monthly_salary' => trim((string)($_POST['monthly_salary'] ?? '')),
            'address' => trim((string)($_POST['address'] ?? '')),
        ];

        $result = $auth->registerNasabah($_POST, $user['id']);
        if ($result['success']) {
            $success = $result['message'];
            $showRegisterForm = false;
            $formData = [
                'nik' => '',
                'full_name' => '',
                'email' => '',
                'phone' => '',
                'division_id' => '',
                'position' => '',
                'member_category' => 'karyawan',
                'payroll_deduction' => '1',
                'employment_date' => '',
                'monthly_salary' => '',
                'address' => '',
            ];
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'toggle_status' && $userId > 0) {
        $target = $db->selectOne("SELECT id, status, role FROM users WHERE id = ?", [$userId]);
        if (!$target) {
            $error = 'Pengguna tidak ditemukan.';
        } elseif ($target['id'] == $user['id']) {
            $error = 'Anda tidak dapat mengubah status akun sendiri.';
        } else {
            $newStatus = $target['status'] === 'active' ? 'inactive' : 'active';
            $db->update("UPDATE users SET status = ? WHERE id = ?", [$newStatus, $userId]);
            $dbHelper->logActivity($user['id'], 'user_status_changed', 'user', $userId, ['status' => $target['status']], ['status' => $newStatus]);
            $success = 'Status pengguna berhasil diperbarui.';
        }
    } elseif ($action === 'update_membership' && $userId > 0) {
        $category = $_POST['member_category'] ?? 'karyawan';
        $payroll = !empty($_POST['payroll_deduction']) ? 1 : 0;
        if (!in_array($category, ['karyawan', 'driver', 'outsourcing'], true)) {
            $error = 'Kategori anggota tidak valid.';
        } else {
            if ($category === 'outsourcing') {
                $payroll = 0;
            }
            try {
                $db->update(
                    "UPDATE users SET member_category = ?, payroll_deduction = ? WHERE id = ? AND role = 'nasabah'",
                    [$category, $payroll, $userId]
                );
                $dbHelper->logActivity($user['id'], 'user_membership_updated', 'user', $userId, null, [
                    'member_category' => $category,
                    'payroll_deduction' => $payroll
                ]);
                $success = 'Kategori anggota & status potongan HRD berhasil diperbarui.';
            } catch (Exception $e) {
                $error = 'Kolom kategori anggota belum tersedia. Jalankan migrasi database/update_koppuja_rat2025_rules.sql terlebih dahulu.';
            }
        }
    }
}

// Filters
$roleFilter = $_GET['role'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$conditions = [];
$params = [];
if ($roleFilter !== 'all') {
    $conditions[] = 'u.role = ?';
    $params[] = $roleFilter;
}
if ($search !== '') {
    $conditions[] = '(u.full_name LIKE ? OR u.nik LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$totalCount = (int)($db->selectOne("SELECT COUNT(*) as total FROM users u $whereClause", $params)['total'] ?? 0);
$totalPages = max(1, ceil($totalCount / $limit));

$users = $db->select(
    "SELECT u.*, d.name as division_name
     FROM users u
     LEFT JOIN divisions d ON u.division_id = d.id
     $whereClause
     ORDER BY u.created_at DESC
     LIMIT $limit OFFSET $offset",
    $params
);

$stats = $db->selectOne(
    "SELECT
        SUM(CASE WHEN role = 'nasabah' THEN 1 ELSE 0 END) as total_nasabah,
        SUM(CASE WHEN role IN ('admin','super_admin') THEN 1 ELSE 0 END) as total_admin,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as total_active,
        SUM(CASE WHEN status != 'active' THEN 1 ELSE 0 END) as total_inactive
     FROM users"
);

renderAdminHeader('Manajemen User - Admin', 'admin-users.php', $user, 'Manajemen User');
?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-primary p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Total Nasabah</h6><h3 class="mb-0"><?php echo (int)$stats['total_nasabah']; ?></h3><i class="fas fa-users card-icon"></i></div></div></div>
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-info p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Total Admin</h6><h3 class="mb-0"><?php echo (int)$stats['total_admin']; ?></h3><i class="fas fa-user-shield card-icon"></i></div></div></div>
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-success p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Akun Aktif</h6><h3 class="mb-0"><?php echo (int)$stats['total_active']; ?></h3><i class="fas fa-user-check card-icon"></i></div></div></div>
    <div class="col-md-3 mb-3"><div class="stats-card stats-gradient-danger p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Akun Nonaktif</h6><h3 class="mb-0"><?php echo (int)$stats['total_inactive']; ?></h3><i class="fas fa-user-slash card-icon"></i></div></div></div>
</div>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <p class="text-muted mb-0">Daftarkan karyawan sebagai nasabah baru agar dapat login dan menggunakan layanan simpan pinjam.</p>
    <button class="btn btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#formDaftarNasabah" aria-expanded="<?php echo $showRegisterForm ? 'true' : 'false'; ?>" aria-controls="formDaftarNasabah">
        <i class="fas fa-user-plus me-1"></i> Daftarkan Nasabah Baru
    </button>
</div>

<div class="collapse <?php echo $showRegisterForm ? 'show' : ''; ?>" id="formDaftarNasabah">
    <div class="card content-card mb-4" id="daftar-nasabah">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-user-plus text-success me-2"></i> Formulir Pendaftaran Nasabah</h5>
        </div>
        <div class="card-body">
            <form method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="register_nasabah">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="nik">NIK <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nik" name="nik" maxlength="20" required
                               value="<?php echo htmlspecialchars($formData['nik']); ?>"
                               placeholder="Contoh: PUN004">
                        <div class="form-text">Huruf/angka, maksimal 20 karakter. Digunakan untuk login.</div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="full_name">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="full_name" name="full_name" maxlength="100" required
                               value="<?php echo htmlspecialchars($formData['full_name']); ?>"
                               placeholder="Nama sesuai data kepegawaian">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="email">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" maxlength="100" required
                               value="<?php echo htmlspecialchars($formData['email']); ?>"
                               placeholder="nama@puninar.com">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="phone">Nomor Telepon <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="phone" name="phone" maxlength="15" required
                               value="<?php echo htmlspecialchars($formData['phone']); ?>"
                               placeholder="081234567890">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="division_id">Divisi</label>
                        <select class="form-select" id="division_id" name="division_id">
                            <option value="">- Pilih Divisi -</option>
                            <?php foreach ($divisions as $division): ?>
                                <option value="<?php echo (int)$division['id']; ?>" <?php echo (string)$formData['division_id'] === (string)$division['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($division['name'] . ' (' . $division['code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="position">Jabatan</label>
                        <input type="text" class="form-control" id="position" name="position" maxlength="50"
                               value="<?php echo htmlspecialchars($formData['position']); ?>"
                               placeholder="Contoh: Driver, Staff Operasional">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="employment_date">Tanggal Masuk Kerja</label>
                        <input type="date" class="form-control" id="employment_date" name="employment_date"
                               value="<?php echo htmlspecialchars($formData['employment_date']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="member_category">Kategori Anggota <span class="text-danger">*</span></label>
                        <select class="form-select" id="member_category" name="member_category" required>
                            <?php foreach (['karyawan' => 'Karyawan', 'driver' => 'Driver', 'outsourcing' => 'Outsourcing'] as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $formData['member_category'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="monthly_salary">Gaji Bulanan</label>
                        <input type="text" class="form-control" id="monthly_salary" name="monthly_salary"
                               value="<?php echo htmlspecialchars($formData['monthly_salary']); ?>"
                               placeholder="Contoh: 5000000">
                        <div class="form-text">Isi angka tanpa Rp, pemisah ribuan opsional.</div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="payroll_deduction" value="1" id="payroll_deduction"
                                   <?php echo $formData['payroll_deduction'] === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="payroll_deduction">Potongan HRD / Yayasan</label>
                            <div class="form-text">Otomatis nonaktif untuk outsourcing.</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="address">Alamat</label>
                        <textarea class="form-control" id="address" name="address" rows="2" placeholder="Alamat lengkap (opsional)"><?php echo htmlspecialchars($formData['address']); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password">Password Awal <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password">
                        <div class="form-text">Minimal 8 karakter, mengandung huruf besar, huruf kecil, dan angka.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="confirm_password">Konfirmasi Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                    </div>
                </div>

                <div class="alert alert-info mt-3 mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    Akun langsung aktif dan terverifikasi. Rekening tabungan wajib akan dibuat otomatis. Nasabah login dengan NIK dan password yang diisi di sini.
                </div>

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="reset" class="btn btn-outline-secondary">Reset</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i> Simpan Akun Nasabah
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card content-card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <select name="role" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>Semua Role</option>
                    <option value="nasabah" <?php echo $roleFilter === 'nasabah' ? 'selected' : ''; ?>>Nasabah</option>
                    <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="super_admin" <?php echo $roleFilter === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                </select>
            </div>
            <div class="col-md-6">
                <input type="text" name="search" class="form-control" placeholder="Cari nama, NIK, atau email..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Cari</button>
            </div>
        </form>
    </div>
</div>

<div class="card content-card">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-users text-primary me-2"></i> Daftar Pengguna (<?php echo $totalCount; ?>)</h5></div>
    <div class="card-body p-0">
        <?php if (empty($users)): ?>
            <div class="text-center text-muted py-5"><i class="fas fa-user-slash fa-3x mb-3"></i><p>Tidak ada pengguna ditemukan.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>Nama</th><th>NIK</th><th>Divisi / Posisi</th><th>Kategori</th><th>Role</th><th>Gaji</th><th>Status</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($u['full_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($u['email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($u['nik']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($u['division_name'] ?? '-'); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($u['position'] ?? '-'); ?></small>
                                </td>
                                <td>
                                    <?php if ($u['role'] === 'nasabah'): ?>
                                        <form method="POST" class="d-flex flex-column gap-1" style="min-width: 160px;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="update_membership">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                            <select name="member_category" class="form-select form-select-sm">
                                                <?php foreach (['karyawan' => 'Karyawan', 'driver' => 'Driver', 'outsourcing' => 'Outsourcing'] as $key => $label): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo ($u['member_category'] ?? 'karyawan') === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-check form-check-inline m-0">
                                                <input class="form-check-input" type="checkbox" name="payroll_deduction" value="1" id="pay_<?php echo (int)$u['id']; ?>"
                                                       <?php echo ((int)($u['payroll_deduction'] ?? 1) === 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="pay_<?php echo (int)$u['id']; ?>">Potongan HRD</label>
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Simpan</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-<?php echo $u['role'] === 'nasabah' ? 'secondary' : 'info'; ?>"><?php echo ucfirst(str_replace('_', ' ', $u['role'])); ?></span></td>
                                <td><?php echo formatRupiah($u['monthly_salary']); ?></td>
                                <td><span class="badge bg-<?php echo $u['status'] === 'active' ? 'success' : 'danger'; ?>"><?php echo ucfirst($u['status']); ?></span></td>
                                <td>
                                    <?php if ($u['id'] != $user['id']): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Ubah status pengguna ini?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-<?php echo $u['status'] === 'active' ? 'danger' : 'success'; ?>">
                                                <i class="fas fa-<?php echo $u['status'] === 'active' ? 'ban' : 'check'; ?>"></i>
                                                <?php echo $u['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan'; ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">Akun Anda</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($totalPages > 1): ?>
    <div class="d-flex justify-content-center mt-4">
        <?php echo generatePagination($page, $totalPages, 'admin-users.php', ['role' => $roleFilter, 'search' => $search]); ?>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const category = document.getElementById('member_category');
    const payroll = document.getElementById('payroll_deduction');
    if (!category || !payroll) return;

    function syncPayroll() {
        const isOutsourcing = category.value === 'outsourcing';
        if (isOutsourcing) {
            payroll.checked = false;
            payroll.disabled = true;
        } else {
            payroll.disabled = false;
        }
    }

    category.addEventListener('change', syncPayroll);
    syncPayroll();
});
</script>

<?php renderAdminFooter(); ?>
