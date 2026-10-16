<?php
/**
 * Admin - Pembayaran Terlambat
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

require_once 'includes/init.php';

$auth->requireAuth();
$auth->requireRole(['admin', 'super_admin']);

$user = $auth->getCurrentUser();

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$where = "WHERE ls.status != 'paid' AND ls.due_date < CURDATE()";

$totalCount = (int)($db->selectOne(
    "SELECT COUNT(*) as total FROM loan_schedules ls JOIN active_loans al ON ls.loan_id = al.id $where"
)['total'] ?? 0);
$totalPages = max(1, ceil($totalCount / $limit));

$overdue = $db->select(
    "SELECT ls.*, al.loan_number, u.full_name, u.nik, u.phone,
            DATEDIFF(CURDATE(), ls.due_date) as days_overdue
     FROM loan_schedules ls
     JOIN active_loans al ON ls.loan_id = al.id
     JOIN users u ON al.user_id = u.id
     $where
     ORDER BY ls.due_date ASC
     LIMIT $limit OFFSET $offset"
);

$totalOverdueAmount = (float)($db->selectOne(
    "SELECT SUM(ls.total_amount) as total FROM loan_schedules ls $where"
)['total'] ?? 0);

renderAdminHeader('Pembayaran Terlambat - Admin', 'admin-active-loans.php', $user, 'Pembayaran Terlambat');
?>

<a href="admin-active-loans.php" class="btn btn-sm btn-outline-secondary mb-3"><i class="fas fa-arrow-left me-1"></i> Kembali</a>

<div class="row mb-4">
    <div class="col-md-4 mb-3"><div class="stats-card stats-gradient-danger p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Total Angsuran Terlambat</h6><h3 class="mb-0"><?php echo $totalCount; ?></h3><i class="fas fa-exclamation-triangle card-icon"></i></div></div></div>
    <div class="col-md-4 mb-3"><div class="stats-card stats-gradient-warning p-3"><div class="position-relative"><h6 class="text-white-50 mb-1">Nilai Tertunggak</h6><h4 class="mb-0"><?php echo formatRupiah($totalOverdueAmount); ?></h4><i class="fas fa-money-bill-wave card-icon"></i></div></div></div>
</div>

<div class="card content-card">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-clock text-danger me-2"></i> Daftar Pembayaran Terlambat</h5></div>
    <div class="card-body p-0">
        <?php if (empty($overdue)): ?>
            <div class="text-center text-muted py-5"><i class="fas fa-check-circle fa-3x mb-3 text-success"></i><p>Tidak ada pembayaran yang terlambat.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>Nasabah</th><th>No. Pinjaman</th><th>Angsuran ke</th><th>Jatuh Tempo</th><th>Terlambat</th><th>Jumlah</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overdue as $row): ?>
                            <tr class="table-danger">
                                <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($row['phone']); ?></small></td>
                                <td><?php echo htmlspecialchars($row['loan_number']); ?></td>
                                <td>#<?php echo (int)$row['installment_number']; ?></td>
                                <td><?php echo formatDateIndo($row['due_date']); ?></td>
                                <td><span class="badge bg-danger"><?php echo (int)$row['days_overdue']; ?> hari</span></td>
                                <td><strong><?php echo formatRupiah($row['total_amount']); ?></strong></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger" onclick="sendReminder(<?php echo $row['id']; ?>)"><i class="fas fa-bell"></i></button>
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
    <div class="d-flex justify-content-center mt-4"><?php echo generatePagination($page, $totalPages, 'admin-overdue.php', []); ?></div>
<?php endif; ?>

<script>
    function sendReminder(scheduleId) {
        if (!confirm('Kirim pengingat pembayaran ke nasabah?')) return;
        fetch('ajax/send-payment-reminder.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ schedule_id: scheduleId })
        })
        .then(r => r.json())
        .then(d => { alert(d.success ? 'Pengingat berhasil dikirim!' : 'Gagal: ' + (d.message || '')); })
        .catch(() => alert('Terjadi kesalahan saat mengirim pengingat.'));
    }
</script>

<?php renderAdminFooter(); ?>
