<?php
/**
 * Utility Functions
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

/**
 * Format currency in Indonesian Rupiah
 */
function formatRupiah($amount, $showCurrency = true) {
    $formatted = number_format($amount, 0, ',', '.');
    return $showCurrency ? 'Rp ' . $formatted : $formatted;
}

/**
 * Format date to Indonesian format
 */
function formatDateIndo($date, $showTime = false) {
    if (!$date || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
        return '-';
    }
    
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    $day = date('d', $timestamp);
    $month = $months[(int)date('m', $timestamp)];
    $year = date('Y', $timestamp);
    
    $result = $day . ' ' . $month . ' ' . $year;
    
    if ($showTime) {
        $result .= ' ' . date('H:i', $timestamp);
    }
    
    return $result;
}

/**
 * Get time ago in Indonesian
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'baru saja';
    if ($time < 3600) return floor($time/60) . ' menit lalu';
    if ($time < 86400) return floor($time/3600) . ' jam lalu';
    if ($time < 2592000) return floor($time/86400) . ' hari lalu';
    if ($time < 31536000) return floor($time/2592000) . ' bulan lalu';
    
    return floor($time/31536000) . ' tahun lalu';
}

/**
 * Generate pagination HTML
 */
function generatePagination($currentPage, $totalPages, $baseUrl, $params = []) {
    if ($totalPages <= 1) return '';
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($currentPage > 1) {
        $prevUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $currentPage - 1]));
        $html .= '<li class="page-item"><a class="page-link" href="' . $prevUrl . '">&laquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
    }
    
    // Page numbers
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $firstUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => 1]));
        $html .= '<li class="page-item"><a class="page-link" href="' . $firstUrl . '">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $pageUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $i]));
            $html .= '<li class="page-item"><a class="page-link" href="' . $pageUrl . '">' . $i . '</a></li>';
        }
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $lastUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $totalPages]));
        $html .= '<li class="page-item"><a class="page-link" href="' . $lastUrl . '">' . $totalPages . '</a></li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $nextUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $currentPage + 1]));
        $html .= '<li class="page-item"><a class="page-link" href="' . $nextUrl . '">&raquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status, $type = 'loan') {
    $badges = [
        'loan' => [
            'pending' => '<span class="badge bg-warning">Pending</span>',
            'reviewing' => '<span class="badge bg-info">Review</span>',
            'approved' => '<span class="badge bg-success">Disetujui</span>',
            'rejected' => '<span class="badge bg-danger">Ditolak</span>',
            'disbursed' => '<span class="badge bg-primary">Dicairkan</span>',
            'active' => '<span class="badge bg-success">Aktif</span>',
            'completed' => '<span class="badge bg-secondary">Lunas</span>',
            'overdue' => '<span class="badge bg-danger">Terlambat</span>'
        ],
        'transaction' => [
            'pending' => '<span class="badge bg-warning">Pending</span>',
            'completed' => '<span class="badge bg-success">Selesai</span>',
            'failed' => '<span class="badge bg-danger">Gagal</span>',
            'cancelled' => '<span class="badge bg-secondary">Dibatalkan</span>'
        ],
        'user' => [
            'active' => '<span class="badge bg-success">Aktif</span>',
            'inactive' => '<span class="badge bg-warning">Nonaktif</span>',
            'resigned' => '<span class="badge bg-secondary">Resign</span>'
        ],
        'payment' => [
            'pending' => '<span class="badge bg-warning">Belum Bayar</span>',
            'paid' => '<span class="badge bg-success">Lunas</span>',
            'overdue' => '<span class="badge bg-danger">Terlambat</span>',
            'partial' => '<span class="badge bg-info">Sebagian</span>'
        ]
    ];
    
    return $badges[$type][$status] ?? '<span class="badge bg-light text-dark">' . ucfirst($status) . '</span>';
}

/**
 * Validasi batas formulir pengajuan pinjaman (BVA TC-24 dan TC-25).
 *
 * @param float      $loanAmount
 * @param int        $termMonths
 * @param float|null $maxLoanAmount Plafon anggota; null = abaikan cek plafon
 * @param int|null   $maxLoanTerm   Tenor kebijakan; default MAX_LOAN_TERM_EXTENDED
 * @return array{valid:bool,message:string,field:?string}
 */
function validateLoanFormInput($loanAmount, $termMonths, $maxLoanAmount = null, $maxLoanTerm = null) {
    $loanAmount = (float)$loanAmount;
    $termMonths = (int)$termMonths;
    $minAmount = defined('MIN_LOAN_AMOUNT') ? (float)MIN_LOAN_AMOUNT : 100000;
    $maxTerm = $maxLoanTerm !== null ? (int)$maxLoanTerm : (defined('MAX_LOAN_TERM_EXTENDED') ? (int)MAX_LOAN_TERM_EXTENDED : 18);

    if ($loanAmount < $minAmount) {
        return [
            'valid' => false,
            'field' => 'loan_amount',
            'message' => 'Jumlah pinjaman minimal ' . formatRupiah($minAmount) . '.',
        ];
    }

    if ($maxLoanAmount !== null && (float)$maxLoanAmount <= 0) {
        return [
            'valid' => false,
            'field' => 'loan_amount',
            'message' => 'Plafon pinjaman belum tersedia. Pastikan simpanan wajib minimal ' . formatRupiah(MIN_MANDATORY_SAVINGS) . '.',
        ];
    }

    if ($maxLoanAmount !== null && $loanAmount > (float)$maxLoanAmount) {
        return [
            'valid' => false,
            'field' => 'loan_amount',
            'message' => 'Jumlah pinjaman melebihi batas maksimal ' . formatRupiah($maxLoanAmount) . '.',
        ];
    }

    if ($termMonths < 1 || $termMonths > $maxTerm) {
        return [
            'valid' => false,
            'field' => 'term_months',
            'message' => 'Jangka waktu harus diisi dan antara 1-' . $maxTerm . ' bulan.',
        ];
    }

    return ['valid' => true, 'field' => null, 'message' => ''];
}

/**
 * Calculate loan installment (flat interest)
 */
function calculateInstallment($loanAmount, $interestRate, $termMonths) {
    $monthlyInterest = ($loanAmount * $interestRate / 100);
    $principal = $loanAmount / $termMonths;
    return $principal + $monthlyInterest;
}

/**
 * Calculate total payment
 */
function calculateTotalPayment($loanAmount, $interestRate, $termMonths) {
    $installment = calculateInstallment($loanAmount, $interestRate, $termMonths);
    return $installment * $termMonths;
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'], $maxSize = null) {
    if (!$maxSize) $maxSize = UPLOAD_MAX_SIZE;
    
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Error uploading file.';
        return $errors;
    }
    
    if ($file['size'] > $maxSize) {
        $errors[] = 'File size too large. Maximum ' . formatBytes($maxSize) . ' allowed.';
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedTypes)) {
        $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes);
    }
    
    return $errors;
}

/**
 * Format file size
 */
function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

/**
 * Upload file to server
 */
function uploadFile($file, $destinationDir, $newName = null) {
    if (!is_dir($destinationDir)) {
        mkdir($destinationDir, 0755, true);
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $fileName = $newName ? $newName . '.' . $extension : uniqid() . '_' . $file['name'];
    $destination = $destinationDir . '/' . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $fileName;
    }
    
    return false;
}

/**
 * Send email notification
 */
function sendEmail($to, $subject, $message, $isHTML = true) {
    // This is a placeholder - implement with your preferred email service
    // You can use PHPMailer, SwiftMailer, or native mail() function
    
    $headers = [
        'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>',
        'Reply-To: ' . FROM_EMAIL,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    if ($isHTML) {
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
    }
    
    return mail($to, $subject, $message, implode("\r\n", $headers));
}

/**
 * Log application activity
 */
function logActivity($message, $level = 'INFO') {
    $logMessage = "[" . date('Y-m-d H:i:s') . "] [{$level}] {$message}" . PHP_EOL;
    file_put_contents(ROOT_PATH . 'logs/application.log', $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Check if user can apply for loan.
 * Pengecekan dijalankan lewat rule engine bila basis aturan tersedia,
 * sehingga aturan kelayakan hanya didefinisikan di satu tempat.
 */
function canApplyLoan($user, $dbHelper) {
    if (class_exists('RuleEngine') && class_exists('LoanManager')) {
        $engine = new RuleEngine();
        
        if ($engine->hasActiveRules(RuleEngine::GROUP_ELIGIBILITY)) {
            $loanManager = new LoanManager();
            $eligibility = $loanManager->checkEligibility($user);
            
            return [
                'can_apply' => $eligibility['eligible'],
                'reason'    => $eligibility['reason'],
                'rule_code' => $eligibility['rule_code'] ?? null
            ];
        }
    }
    
    return canApplyLoanLegacy($user, $dbHelper);
}

/**
 * Aturan kelayakan bawaan (dipakai bila basis aturan belum dimigrasikan)
 */
function canApplyLoanLegacy($user, $dbHelper) {
    // Ensure employment_date is available; fetch from DB if missing from session payload
    $employmentDateStr = $user['employment_date'] ?? null;
    if (!$employmentDateStr && !empty($user['id'])) {
        $fullUser = $dbHelper->getUserById($user['id']);
        if ($fullUser && !empty($fullUser['employment_date'])) {
            $employmentDateStr = $fullUser['employment_date'];
        }
    }
    
    // If still missing, block application with a clear reason
    if (empty($employmentDateStr)) {
        return [
            'can_apply' => false,
            'reason' => 'Data kepegawaian belum lengkap. Tanggal mulai bekerja belum tersedia.'
        ];
    }
    
    // Check employment duration safely
    try {
        $employmentDate = new DateTime($employmentDateStr);
        $now = new DateTime();
        $diff = $employmentDate->diff($now);
        $employmentMonths = ($diff->y * 12) + $diff->m;
    } catch (Exception $e) {
        return [
            'can_apply' => false,
            'reason' => 'Format tanggal mulai bekerja tidak valid.'
        ];
    }
    
    if ($employmentMonths < MIN_EMPLOYMENT_MONTHS) {
        return [
            'can_apply' => false,
            'reason' => 'Masa kerja minimal ' . MIN_EMPLOYMENT_MONTHS . ' bulan.'
        ];
    }
    
    // Check active loan
    $activeLoan = $dbHelper->getActiveLoanByUserId($user['id']);
    if ($activeLoan) {
        return [
            'can_apply' => false,
            'reason' => 'Anda sudah memiliki pinjaman aktif.'
        ];
    }
    
    return ['can_apply' => true];
}

/**
 * Apakah anggota potongan angsuran melalui HRD/Yayasan.
 * Outsourcing atau tanpa potongan HRD => plafon 1x simpanan wajib.
 */
function hasPayrollDeduction($user) {
    $category = strtolower(trim((string)($user['member_category'] ?? 'karyawan')));
    if ($category === 'outsourcing' || $category === 'os' || $category === 'yayasan_os') {
        return false;
    }
    if (array_key_exists('payroll_deduction', $user)) {
        return (int)$user['payroll_deduction'] === 1;
    }
    return true;
}

/**
 * Kelipatan plafon pinjaman terhadap simpanan wajib (1x atau 2x).
 */
function getLoanMultiplierForUser($user, $dbHelper = null) {
    $standard = MAX_LOAN_MULTIPLIER;
    $limited = MAX_LOAN_MULTIPLIER_LIMITED;

    if ($dbHelper) {
        $settingStandard = $dbHelper->getSystemSetting('max_loan_multiplier');
        $settingLimited = $dbHelper->getSystemSetting('max_loan_multiplier_limited');
        if ($settingStandard !== null && $settingStandard !== '') {
            $standard = (float)$settingStandard;
        }
        if ($settingLimited !== null && $settingLimited !== '') {
            $limited = (float)$settingLimited;
        }
    }

    return hasPayrollDeduction($user) ? $standard : $limited;
}

/**
 * Label kategori anggota untuk UI.
 */
function getMemberCategoryLabel($category) {
    $map = [
        'karyawan' => 'Karyawan',
        'driver' => 'Driver / Pengemudi',
        'outsourcing' => 'Outsourcing / Yayasan (angsur mandiri)',
    ];
    $key = strtolower(trim((string)$category));
    return $map[$key] ?? 'Karyawan';
}

/**
 * Maksimal pinjaman = kelipatan simpanan wajib sesuai kategori anggota (RAT 2025).
 *
 * @param array|float $userOrSalary Data user (disarankan) atau gaji lama (kompatibilitas)
 * @param float|null  $savingsBalance Saldo simpanan wajib (opsional; diambil otomatis bila null)
 * @param object|null $dbHelper
 */
function getMaxLoanAmount($userOrSalary, $savingsBalance = null, $dbHelper = null) {
    $helper = $dbHelper;
    if ($helper === null && isset($GLOBALS['dbHelper'])) {
        $helper = $GLOBALS['dbHelper'];
    }

    // Kompatibilitas panggilan lama getMaxLoanAmount($salary) — tidak lagi dipakai sebagai basis plafon.
    if (!is_array($userOrSalary)) {
        $salary = (float)$userOrSalary;
        return max(0, $salary * MAX_LOAN_MULTIPLIER);
    }

    $user = $userOrSalary;
    if ($helper && !empty($user['id']) && (!isset($user['member_category']) || !array_key_exists('payroll_deduction', $user))) {
        $fullUser = $helper->getUserById($user['id']);
        if ($fullUser) {
            $user = $fullUser;
        }
    }

    if ($savingsBalance === null) {
        $savingsBalance = 0;
        if ($helper && !empty($user['id'])) {
            $account = $helper->getSavingsAccountByUserId($user['id']);
            $savingsBalance = (float)($account['balance'] ?? 0);
        }
    }

    $multiplier = getLoanMultiplierForUser($user, $helper);
    return max(0, floor(((float)$savingsBalance) * $multiplier));
}

/**
 * Tenor maksimal absolut (kebijakan khusus 18 bulan).
 */
function getMaxLoanTerm($dbHelper = null) {
    $helper = $dbHelper;
    if ($helper === null && isset($GLOBALS['dbHelper'])) {
        $helper = $GLOBALS['dbHelper'];
    }
    if ($helper) {
        $val = $helper->getSystemSetting('max_loan_term');
        if ($val !== null && $val !== '') {
            return (int)$val;
        }
    }
    return MAX_LOAN_TERM_EXTENDED;
}

/**
 * Tenor standar tanpa surat pernyataan (12 bulan).
 */
function getStandardLoanTerm($dbHelper = null) {
    $helper = $dbHelper;
    if ($helper === null && isset($GLOBALS['dbHelper'])) {
        $helper = $GLOBALS['dbHelper'];
    }
    if ($helper) {
        $val = $helper->getSystemSetting('max_loan_term_standard');
        if ($val !== null && $val !== '') {
            return (int)$val;
        }
    }
    return MAX_LOAN_TERM_STANDARD;
}

/**
 * Generate breadcrumb navigation
 */
function generateBreadcrumb($items) {
    $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    
    $total = count($items);
    $current = 0;
    
    foreach ($items as $label => $url) {
        $current++;
        if ($current == $total) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . $label . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item"><a href="' . $url . '">' . $label . '</a></li>';
        }
    }
    
    $html .= '</ol></nav>';
    return $html;
}

/**
 * Truncate text
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . $suffix;
}

/**
 * Get notification icon based on type
 */
function getNotificationIcon($type) {
    $icons = [
        'info' => 'fas fa-info-circle text-info',
        'warning' => 'fas fa-exclamation-triangle text-warning',
        'success' => 'fas fa-check-circle text-success',
        'error' => 'fas fa-times-circle text-danger'
    ];
    
    return $icons[$type] ?? $icons['info'];
}

/**
 * Generate secure file name
 */
function generateSecureFileName($originalName) {
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    return uniqid() . '_' . time() . '.' . $extension;
}

/**
 * Check if date is overdue
 */
function isOverdue($date) {
    return strtotime($date) < strtotime(date('Y-m-d'));
}

/**
 * Get days until due date
 */
function getDaysUntilDue($dueDate) {
    $today = new DateTime();
    $due = new DateTime($dueDate);
    $diff = $today->diff($due);
    
    if ($due < $today) {
        return -$diff->days; // Negative means overdue
    }
    
    return $diff->days;
}

/**
 * Format phone number
 */
function formatPhoneNumber($phone) {
    // Remove all non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Convert to standard format
    if (substr($phone, 0, 1) == '0') {
        $phone = '62' . substr($phone, 1);
    } elseif (substr($phone, 0, 2) != '62') {
        $phone = '62' . $phone;
    }
    
    return $phone;
}

/**
 * Check working hours
 */
function isWorkingHours() {
    $hour = (int)date('H');
    $day = date('N'); // 1 = Monday, 7 = Sunday
    
    // Monday to Friday, 8 AM to 5 PM
    return ($day >= 1 && $day <= 5) && ($hour >= 8 && $hour <= 17);
}

/**
 * Get next working day
 */
function getNextWorkingDay($date = null) {
    $date = $date ? new DateTime($date) : new DateTime();
    
    do {
        $date->add(new DateInterval('P1D'));
    } while ($date->format('N') >= 6); // Skip weekends
    
    return $date->format('Y-m-d');
}

?>
