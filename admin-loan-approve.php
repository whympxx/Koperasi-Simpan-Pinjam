<?php
/**
 * Admin - Quick redirect to loan detail for approval
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

require_once 'includes/init.php';

$auth->requireAuth();
$auth->requireRole(['admin', 'super_admin']);

$applicationId = (int)($_GET['id'] ?? 0);

header('Location: admin-loan-detail.php?id=' . $applicationId);
exit;
