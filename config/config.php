<?php
/**
 * Konfigurasi Aplikasi Simpan Pinjam PT. Puninar Jaya Logistik
 * 
 * @author System Developer
 * @version 1.0
 * @since 2024
 */

// Prevent direct access
if (!defined('APP_ACCESS')) {
    die('Direct access not allowed');
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'simpanpinjam_puninar');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'KOPPUJA - Simpan Pinjam');
define('APP_FULL_NAME', 'Koperasi Konsumen Karyawan PT. Puninar Jaya (KOPPUJA)');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/Aplikas_%20SimpanPinjamNasabahPTpuninarjaya/');
define('APP_TIMEZONE', 'Asia/Jakarta');

// Security Configuration
define('SECRET_KEY', 'PuninarJaya2024!@#SecretKey');
define('SESSION_NAME', 'puninar_session');
define('SESSION_TIMEOUT', 900); // 15 minutes in seconds
define('CSRF_TOKEN_NAME', 'csrf_token');
define('HASH_ALGORITHM', 'sha256');

// File Upload Configuration
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// Email Configuration (for notifications)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@puninar.com');
define('SMTP_PASSWORD', '');
define('SMTP_ENCRYPTION', 'tls');
define('FROM_EMAIL', 'noreply@puninar.com');
define('FROM_NAME', 'PT. Puninar Jaya Logistik');

// Pagination Configuration
define('RECORDS_PER_PAGE', 20);
define('MAX_PAGINATION_LINKS', 10);

// Application Paths
define('ROOT_PATH', __DIR__ . '/../');
define('INCLUDES_PATH', ROOT_PATH . 'includes/');
define('CLASSES_PATH', ROOT_PATH . 'classes/');
define('TEMPLATES_PATH', ROOT_PATH . 'templates/');
define('ASSETS_PATH', ROOT_PATH . 'assets/');

// Business Logic Configuration (sesuai ketentuan KOPPUJA RAT Tahun Buku 2025)
define('MIN_EMPLOYMENT_MONTHS', 12);
define('MAX_LOAN_MULTIPLIER', 2);              // plafon standar: 2x simpanan wajib (HRD/Yayasan potong gaji)
define('MAX_LOAN_MULTIPLIER_LIMITED', 1);      // plafon terbatas: 1x simpanan wajib (outsourcing / angsur mandiri)
define('MAX_LOAN_TERM_STANDARD', 12);          // tenor standar maksimal (bulan)
define('MAX_LOAN_TERM_EXTENDED', 18);          // tenor kebijakan khusus (butuh surat pernyataan)
define('MIN_MANDATORY_SAVINGS', 50000);        // minimal simpanan wajib
define('MIN_LOAN_AMOUNT', 100000);             // nominal minimum pengajuan pada formulir
define('DEFAULT_LOAN_INTEREST_RATE', 1.5);
define('DEFAULT_SAVINGS_INTEREST_RATE', 0);    // simpanan sukarela ditiadakan (RAT ke-19)
define('LATE_FEE_PERCENTAGE', 0.5);
define('VOLUNTARY_SAVINGS_ENABLED', false);    // simpanan sukarela tidak dilayani

// Auto-Approval Configuration
// Pinjaman yang memenuhi kriteria di bawah ini akan disetujui otomatis oleh sistem
// tanpa perlu review manual dari admin.
define('LOAN_AUTO_APPROVE_ENABLED', true);
define('LOAN_AUTO_APPROVE_MAX_AMOUNT', 5000000); // batas maksimal jumlah pinjaman untuk auto-approve
define('LOAN_AUTO_APPROVE_MAX_TERM_MONTHS', 12); // batas maksimal tenor (bulan) untuk auto-approve

// Status Constants
define('USER_STATUS_ACTIVE', 'active');
define('USER_STATUS_INACTIVE', 'inactive');
define('USER_STATUS_RESIGNED', 'resigned');

define('LOAN_STATUS_PENDING', 'pending');
define('LOAN_STATUS_REVIEWING', 'reviewing');
define('LOAN_STATUS_APPROVED', 'approved');
define('LOAN_STATUS_REJECTED', 'rejected');
define('LOAN_STATUS_DISBURSED', 'disbursed');

define('ACTIVE_LOAN_STATUS_ACTIVE', 'active');
define('ACTIVE_LOAN_STATUS_COMPLETED', 'completed');
define('ACTIVE_LOAN_STATUS_OVERDUE', 'overdue');

// Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set Timezone
date_default_timezone_set(APP_TIMEZONE);

// Start Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

?>
