<?php
/**
 * Authentication and Session Management Class
 * PT. Puninar Jaya Logistik - Simpan Pinjam System
 */

class Auth {
    private $db;
    private $dbHelper;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->dbHelper = new DatabaseHelper();
        
        // Start session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
        
        $this->checkSessionTimeout();
    }
    
    /**
     * Authenticate user login
     */
    public function login($nik, $password, $rememberMe = false) {
        try {
            // Get user by NIK
            $user = $this->dbHelper->getUserByNik($nik);
            
            if (!$user) {
                return ['success' => false, 'message' => 'NIK tidak ditemukan atau akun tidak aktif.'];
            }
            
            // Verify password
            if (!password_verify($password, $user['password'])) {
                // Log failed login attempt
                $this->logFailedLogin($nik);
                return ['success' => false, 'message' => 'Password salah.'];
            }
            
            // Check if account is verified
            if (!$user['is_verified']) {
                return ['success' => false, 'message' => 'Akun Anda belum diverifikasi. Hubungi administrator.'];
            }
            
            // Update last login
            $this->updateLastLogin($user['id']);
            
            // Create session
            $this->createSession($user);
            
            // Handle remember me
            if ($rememberMe) {
                $this->setRememberMeCookie($user['id']);
            }
            
            // Log successful login
            $this->dbHelper->logActivity($user['id'], 'login', 'user', $user['id']);
            
            return [
                'success' => true, 
                'message' => 'Login berhasil.',
                'redirect' => $this->getRedirectUrl($user['role'])
            ];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.'];
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if ($this->isLoggedIn()) {
            $userId = $_SESSION['user_id'];
            
            // Log logout activity
            $this->dbHelper->logActivity($userId, 'logout', 'user', $userId);
        }
        
        // Clear session
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Clear remember me cookie
        if (isset($_COOKIE['remember_me'])) {
            setcookie('remember_me', '', time() - 3600, '/');
        }
        
        session_destroy();
        
        return true;
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'nik' => $_SESSION['user_nik'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role'],
            'division' => $_SESSION['user_division'] ?? null,
            'position' => $_SESSION['user_position'] ?? null,
            'salary' => $_SESSION['user_salary'] ?? 0
        ];
    }
    
    /**
     * Check user role
     */
    public function hasRole($role) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        if (is_array($role)) {
            return in_array($_SESSION['user_role'], $role);
        }
        
        return $_SESSION['user_role'] === $role;
    }
    
    /**
     * Require authentication
     */
    public function requireAuth($redirectTo = 'login.php') {
        if (!$this->isLoggedIn()) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }
    
    /**
     * Require specific role
     */
    public function requireRole($role, $redirectTo = 'access-denied.php') {
        $this->requireAuth();
        
        if (!$this->hasRole($role)) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }
    
    /**
     * Register a new nasabah account (admin only).
     * Creates the user record and opens a savings account automatically.
     *
     * @param array $data Form data
     * @param int|null $createdBy Admin user id
     * @return array{success:bool,message:string,user_id?:int,account_number?:string}
     */
    public function registerNasabah($data, $createdBy = null) {
        try {
            $nik = strtoupper(trim((string)($data['nik'] ?? '')));
            $fullName = SecurityHelper::sanitize($data['full_name'] ?? '');
            $email = strtolower(trim((string)($data['email'] ?? '')));
            $phone = preg_replace('/\s+/', '', (string)($data['phone'] ?? ''));
            $password = (string)($data['password'] ?? '');
            $confirmPassword = (string)($data['confirm_password'] ?? '');
            $divisionId = (int)($data['division_id'] ?? 0);
            $position = SecurityHelper::sanitize($data['position'] ?? '');
            $memberCategory = $data['member_category'] ?? 'karyawan';
            $payrollDeduction = !empty($data['payroll_deduction']) ? 1 : 0;
            $employmentDate = trim((string)($data['employment_date'] ?? ''));
            $address = SecurityHelper::sanitize($data['address'] ?? '');
            $salaryRaw = (string)($data['monthly_salary'] ?? '0');
            $monthlySalary = (float)str_replace(['.', ','], ['', '.'], $salaryRaw);

            if ($nik === '' || $fullName === '' || $email === '' || $phone === '' || $password === '' || $confirmPassword === '') {
                return ['success' => false, 'message' => 'NIK, nama lengkap, email, telepon, dan password wajib diisi.'];
            }

            if (!SecurityHelper::validateNIK($nik)) {
                return ['success' => false, 'message' => 'Format NIK tidak valid. Gunakan huruf/angka maksimal 20 karakter.'];
            }

            if (!SecurityHelper::validateEmail($email)) {
                return ['success' => false, 'message' => 'Format email tidak valid.'];
            }

            if (!SecurityHelper::validatePhone($phone)) {
                return ['success' => false, 'message' => 'Format nomor telepon tidak valid. Gunakan format Indonesia, contoh 081234567890.'];
            }

            if ($password !== $confirmPassword) {
                return ['success' => false, 'message' => 'Password dan konfirmasi password tidak sama.'];
            }

            $passwordErrors = $this->validatePassword($password);
            if (!empty($passwordErrors)) {
                return ['success' => false, 'message' => implode(' ', $passwordErrors)];
            }

            if (!in_array($memberCategory, ['karyawan', 'driver', 'outsourcing'], true)) {
                return ['success' => false, 'message' => 'Kategori anggota tidak valid.'];
            }

            if ($memberCategory === 'outsourcing') {
                $payrollDeduction = 0;
            }

            if ($monthlySalary < 0) {
                return ['success' => false, 'message' => 'Gaji bulanan tidak boleh negatif.'];
            }

            if ($employmentDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $employmentDate)) {
                return ['success' => false, 'message' => 'Format tanggal masuk kerja tidak valid.'];
            }

            if ($divisionId > 0) {
                $division = $this->db->selectOne("SELECT id FROM divisions WHERE id = ?", [$divisionId]);
                if (!$division) {
                    return ['success' => false, 'message' => 'Divisi tidak ditemukan.'];
                }
            } else {
                $divisionId = null;
            }

            $existingNik = $this->db->selectOne("SELECT id FROM users WHERE nik = ?", [$nik]);
            if ($existingNik) {
                return ['success' => false, 'message' => 'NIK sudah terdaftar. Gunakan NIK lain.'];
            }

            $existingEmail = $this->db->selectOne("SELECT id FROM users WHERE email = ?", [$email]);
            if ($existingEmail) {
                return ['success' => false, 'message' => 'Email sudah terdaftar. Gunakan email lain.'];
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $employmentDateValue = $employmentDate !== '' ? $employmentDate : null;
            $positionValue = $position !== '' ? $position : null;
            $addressValue = $address !== '' ? $address : null;

            $this->db->beginTransaction();

            $userId = $this->db->insert(
                "INSERT INTO users (
                    nik, full_name, email, phone, password, role, division_id, position,
                    member_category, payroll_deduction, employment_date, monthly_salary,
                    address, status, is_verified
                 ) VALUES (?, ?, ?, ?, ?, 'nasabah', ?, ?, ?, ?, ?, ?, ?, 'active', 1)",
                [
                    $nik,
                    $fullName,
                    $email,
                    $phone,
                    $hashedPassword,
                    $divisionId,
                    $positionValue,
                    $memberCategory,
                    $payrollDeduction,
                    $employmentDateValue,
                    $monthlySalary,
                    $addressValue
                ]
            );

            if (!$userId) {
                $this->db->rollback();
                return ['success' => false, 'message' => 'Gagal menyimpan akun nasabah.'];
            }

            $savingsManager = new SavingsManager();
            $savingsResult = $savingsManager->createAccount((int)$userId);
            if (empty($savingsResult['success'])) {
                $this->db->rollback();
                return ['success' => false, 'message' => 'Akun nasabah gagal dibuat karena rekening tabungan tidak dapat dibuka.'];
            }

            $this->dbHelper->logActivity(
                $createdBy ?: (int)$userId,
                'nasabah_registered',
                'user',
                (int)$userId,
                null,
                [
                    'nik' => $nik,
                    'full_name' => $fullName,
                    'email' => $email,
                    'member_category' => $memberCategory,
                    'account_number' => $savingsResult['account_number'] ?? null
                ]
            );

            $this->dbHelper->createNotification(
                (int)$userId,
                'Akun Nasabah Siap Digunakan',
                'Akun Anda telah didaftarkan oleh admin. Silakan login menggunakan NIK ' . $nik . ' dan password yang diberikan.',
                'success',
                'account_activity'
            );

            $this->db->commit();

            $accountNumber = $savingsResult['account_number'] ?? '-';
            return [
                'success' => true,
                'message' => 'Nasabah ' . $fullName . ' berhasil didaftarkan. NIK: ' . $nik . ', No. Rekening: ' . $accountNumber . '.',
                'user_id' => (int)$userId,
                'account_number' => $accountNumber
            ];

        } catch (Exception $e) {
            try {
                $this->db->rollback();
            } catch (Exception $rollbackError) {
                // ignore if no active transaction
            }
            error_log("Register nasabah error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem saat mendaftarkan nasabah. Silakan coba lagi.'];
        }
    }

    /**
     * Change password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Get user data
            $user = $this->dbHelper->getUserById($userId);
            
            if (!$user) {
                return ['success' => false, 'message' => 'Pengguna tidak ditemukan.'];
            }
            
            // Verify current password
            if (!password_verify($currentPassword, $user['password'])) {
                return ['success' => false, 'message' => 'Password lama salah.'];
            }
            
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
            $query = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
            $this->db->update($query, [$hashedPassword, $userId]);
            
            // Log activity
            $this->dbHelper->logActivity($userId, 'change_password', 'user', $userId);
            
            // Send notification
            $this->dbHelper->createNotification(
                $userId,
                'Password Berhasil Diubah',
                'Password akun Anda telah berhasil diubah pada ' . date('d/m/Y H:i:s'),
                'success',
                'account_activity'
            );
            
            return ['success' => true, 'message' => 'Password berhasil diubah.'];
            
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.'];
        }
    }
    
    /**
     * Reset password
     */
    public function resetPassword($nik, $newPassword) {
        try {
            $user = $this->dbHelper->getUserByNik($nik);
            
            if (!$user) {
                return ['success' => false, 'message' => 'NIK tidak ditemukan.'];
            }
            
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
            $query = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
            $this->db->update($query, [$hashedPassword, $user['id']]);
            
            // Log activity
            $this->dbHelper->logActivity($user['id'], 'password_reset', 'user', $user['id']);
            
            return ['success' => true, 'message' => 'Password berhasil direset.'];
            
        } catch (Exception $e) {
            error_log("Reset password error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.'];
        }
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCSRFToken($token) {
        return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * Create user session
     */
    private function createSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nik'] = $user['nik'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_division'] = $user['division_name'];
        $_SESSION['user_position'] = $user['position'];
        $_SESSION['user_salary'] = $user['monthly_salary'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
    }
    
    /**
     * Check session timeout
     */
    private function checkSessionTimeout() {
        if (isset($_SESSION['last_activity'])) {
            $inactive = time() - $_SESSION['last_activity'];
            
            if ($inactive >= SESSION_TIMEOUT) {
                $this->logout();
                header('Location: login.php?timeout=1');
                exit;
            }
        }
        
        $_SESSION['last_activity'] = time();
    }
    
    /**
     * Update last login time
     */
    private function updateLastLogin($userId) {
        $query = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $this->db->update($query, [$userId]);
    }
    
    /**
     * Log failed login attempt
     */
    private function logFailedLogin($nik) {
        $query = "INSERT INTO activity_logs (user_id, action, entity_type, old_values, ip_address, user_agent) 
                  VALUES (NULL, 'failed_login', 'user', ?, ?, ?)";
        
        $data = json_encode(['nik' => $nik, 'timestamp' => date('Y-m-d H:i:s')]);
        $params = [
            $data,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ];
        
        $this->db->insert($query, $params);
    }
    
    /**
     * Set remember me cookie
     */
    private function setRememberMeCookie($userId) {
        $token = bin2hex(random_bytes(32));
        $expiry = time() + (30 * 24 * 60 * 60); // 30 days
        
        // Store token in database
        $query = "UPDATE users SET remember_token = ?, remember_token_expires = FROM_UNIXTIME(?) WHERE id = ?";
        $this->db->update($query, [$token, $expiry, $userId]);
        
        // Set cookie
        setcookie('remember_me', $token, $expiry, '/', '', false, true);
    }
    
    /**
     * Get redirect URL based on user role
     */
    private function getRedirectUrl($role) {
        switch ($role) {
            case 'super_admin':
            case 'admin':
                return 'admin-dashboard.php';
            case 'nasabah':
            default:
                return 'dashboard.php';
        }
    }
    
    /**
     * Validate password strength
     */
    public function validatePassword($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password minimal 8 karakter.';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password harus mengandung huruf besar.';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password harus mengandung huruf kecil.';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password harus mengandung angka.';
        }
        
        return $errors;
    }
    
    /**
     * Generate OTP code
     */
    public function generateOTP($userId, $purpose, $phoneNumber) {
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = date('Y-m-d H:i:s', time() + 300); // 5 minutes
        
        // Clean old OTPs
        $this->db->delete("DELETE FROM otp_verifications WHERE user_id = ? AND purpose = ?", [$userId, $purpose]);
        
        // Insert new OTP
        $query = "INSERT INTO otp_verifications (user_id, otp_code, purpose, phone_number, expires_at) VALUES (?, ?, ?, ?, ?)";
        $this->db->insert($query, [$userId, $otp, $purpose, $phoneNumber, $expiresAt]);
        
        return $otp;
    }
    
    /**
     * Verify OTP code
     */
    public function verifyOTP($userId, $otpCode, $purpose) {
        $query = "SELECT * FROM otp_verifications 
                  WHERE user_id = ? AND otp_code = ? AND purpose = ? AND is_used = 0 AND expires_at > NOW()";
        
        $otp = $this->db->selectOne($query, [$userId, $otpCode, $purpose]);
        
        if ($otp) {
            // Mark OTP as used
            $this->db->update("UPDATE otp_verifications SET is_used = 1 WHERE id = ?", [$otp['id']]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get user by NIK and email
     */
    public function getUserByNikAndEmail($nik, $email) {
        $query = "SELECT u.*, d.name as division_name, d.code as division_code 
                  FROM users u 
                  LEFT JOIN divisions d ON u.division_id = d.id 
                  WHERE u.nik = ? AND u.email = ? AND u.status = 'active'";
        return $this->db->selectOne($query, [$nik, $email]);
    }
    
    /**
     * Generate password reset token
     */
    public function generatePasswordResetToken($userId) {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour
        
        // Clean old tokens for this user
        $this->db->delete("DELETE FROM password_reset_tokens WHERE user_id = ?", [$userId]);
        
        // Insert new token
        $query = "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)";
        $this->db->insert($query, [$userId, $token, $expiresAt]);
        
        return $token;
    }
    
    /**
     * Validate password reset token
     */
    public function validatePasswordResetToken($token) {
        $query = "SELECT prt.*, u.nik, u.full_name, u.email 
                  FROM password_reset_tokens prt 
                  JOIN users u ON prt.user_id = u.id 
                  WHERE prt.token = ? AND prt.is_used = 0 AND prt.expires_at > NOW()";
        
        return $this->db->selectOne($query, [$token]);
    }
    
    /**
     * Reset password with token
     */
    public function resetPasswordWithToken($token, $newPassword) {
        try {
            $tokenData = $this->validatePasswordResetToken($token);
            
            if (!$tokenData) {
                return ['success' => false, 'message' => 'Token tidak valid atau sudah kadaluarsa.'];
            }
            
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
            $query = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
            $this->db->update($query, [$hashedPassword, $tokenData['user_id']]);
            
            // Mark token as used
            $this->db->update("UPDATE password_reset_tokens SET is_used = 1 WHERE id = ?", [$tokenData['id']]);
            
            // Log activity
            $this->dbHelper->logActivity($tokenData['user_id'], 'password_reset', 'user', $tokenData['user_id']);
            
            // Send notification
            $this->dbHelper->createNotification(
                $tokenData['user_id'],
                'Password Berhasil Direset',
                'Password akun Anda telah berhasil direset pada ' . date('d/m/Y H:i:s'),
                'success',
                'account_activity'
            );
            
            return ['success' => true, 'message' => 'Password berhasil direset.'];
            
        } catch (Exception $e) {
            error_log("Reset password with token error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem. Silakan coba lagi.'];
        }
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($email, $name, $resetLink) {
        try {
            $emailHelper = new EmailHelper();
            return $emailHelper->sendPasswordResetEmail($email, $name, $resetLink);
        } catch (Exception $e) {
            error_log("Send password reset email error: " . $e->getMessage());
            return false;
        }
    }
    
}

/**
 * Security Helper Class
 */
class SecurityHelper {
    
    /**
     * Sanitize input data
     */
    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate phone number (Indonesia format)
     */
    public static function validatePhone($phone) {
        $pattern = '/^(\+62|62|0)8[1-9][0-9]{6,9}$/';
        return preg_match($pattern, $phone);
    }
    
    /**
     * Validate NIK (alphanumeric, 1-20 characters)
     */
    public static function validateNIK($nik) {
        return preg_match('/^[A-Za-z0-9]{1,20}$/', $nik);
    }
    
    /**
     * Generate secure random string
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Hash data with secret key
     */
    public static function hashData($data) {
        return hash_hmac(HASH_ALGORITHM, $data, SECRET_KEY);
    }
    
    /**
     * Verify hashed data
     */
    public static function verifyHash($data, $hash) {
        return hash_equals(self::hashData($data), $hash);
    }
    
    /**
     * Encrypt sensitive data
     */
    public static function encrypt($data) {
        $key = substr(hash('sha256', SECRET_KEY), 0, 32);
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }
    
    /**
     * Decrypt sensitive data
     */
    public static function decrypt($data) {
        $key = substr(hash('sha256', SECRET_KEY), 0, 32);
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($encrypted_data, 'AES-256-CBC', $key, 0, $iv);
    }
}

?>
