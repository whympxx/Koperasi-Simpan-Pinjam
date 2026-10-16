<?php
require_once 'includes/init.php';

$auth->requireAuth();
$auth->requireRole('nasabah');

$_SESSION['error_message'] = 'Simpanan wajib bersifat beku dan tidak dapat diambil selama masih menjadi anggota koperasi (ketentuan KOPPUJA RAT 2025).';
header('Location: savings.php');
exit;
