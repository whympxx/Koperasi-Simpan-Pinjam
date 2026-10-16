-- ====================================================
-- DATABASE SIMPAN PINJAM PT. PUNINAR JAYA LOGISTIK
-- ====================================================

CREATE DATABASE IF NOT EXISTS simpanpinjam_puninar;
USE simpanpinjam_puninar;

-- Tabel Divisi/Departemen
CREATE TABLE divisions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Users (Karyawan dan Admin)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nik VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(15) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('nasabah', 'admin', 'super_admin') DEFAULT 'nasabah',
    division_id INT,
    position VARCHAR(50),
    member_category ENUM('karyawan','driver','outsourcing') NOT NULL DEFAULT 'karyawan',
    payroll_deduction TINYINT(1) NOT NULL DEFAULT 1,
    employment_date DATE,
    monthly_salary DECIMAL(15,2),
    status ENUM('active', 'inactive', 'resigned') DEFAULT 'active',
    profile_photo VARCHAR(255),
    address TEXT,
    is_verified BOOLEAN DEFAULT FALSE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (division_id) REFERENCES divisions(id)
);

-- Tabel Akun Tabungan
CREATE TABLE savings_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    account_number VARCHAR(20) UNIQUE NOT NULL,
    balance DECIMAL(15,2) DEFAULT 0.00,
    interest_rate DECIMAL(5,2) DEFAULT 2.50,
    status ENUM('active', 'inactive', 'closed') DEFAULT 'active',
    opened_date DATE NOT NULL,
    last_transaction_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabel Pengajuan Pinjaman
CREATE TABLE loan_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    application_number VARCHAR(30) UNIQUE NOT NULL,
    loan_amount DECIMAL(15,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    loan_term_months INT NOT NULL,
    monthly_installment DECIMAL(15,2) NOT NULL,
    total_payment DECIMAL(15,2) NOT NULL,
    purpose VARCHAR(100) NOT NULL,
    description TEXT,
    collateral TEXT,
    status ENUM('pending', 'reviewing', 'approved', 'rejected', 'disbursed') DEFAULT 'pending',
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT,
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    disbursed_at TIMESTAMP NULL,
    rejected_reason TEXT,
    documents JSON,
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(15),
    emergency_contact_relation VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Tabel Pinjaman Aktif
CREATE TABLE active_loans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    application_id INT NOT NULL,
    loan_number VARCHAR(30) UNIQUE NOT NULL,
    original_amount DECIMAL(15,2) NOT NULL,
    remaining_balance DECIMAL(15,2) NOT NULL,
    monthly_installment DECIMAL(15,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    loan_term_months INT NOT NULL,
    remaining_months INT NOT NULL,
    start_date DATE NOT NULL,
    next_due_date DATE NOT NULL,
    status ENUM('active', 'completed', 'overdue', 'restructured') DEFAULT 'active',
    total_paid DECIMAL(15,2) DEFAULT 0.00,
    late_fees DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES loan_applications(id)
);

-- Tabel Transaksi
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    reference_number VARCHAR(30) UNIQUE NOT NULL,
    type ENUM('savings_deposit', 'savings_withdrawal', 'loan_disbursement', 'loan_payment', 'interest_credit', 'fee_debit') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    description TEXT,
    related_account_id INT NULL,
    related_loan_id INT NULL,
    balance_before DECIMAL(15,2),
    balance_after DECIMAL(15,2),
    payment_method ENUM('cash', 'transfer', 'payroll_deduction', 'online') DEFAULT 'cash',
    payment_reference VARCHAR(100),
    processed_by INT NULL,
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'completed',
    transaction_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_account_id) REFERENCES savings_accounts(id),
    FOREIGN KEY (related_loan_id) REFERENCES active_loans(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
);

-- Tabel Jadwal Angsuran
CREATE TABLE loan_schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    loan_id INT NOT NULL,
    installment_number INT NOT NULL,
    due_date DATE NOT NULL,
    principal_amount DECIMAL(15,2) NOT NULL,
    interest_amount DECIMAL(15,2) NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    paid_amount DECIMAL(15,2) DEFAULT 0.00,
    paid_date DATE NULL,
    late_fee DECIMAL(15,2) DEFAULT 0.00,
    status ENUM('pending', 'paid', 'overdue', 'partial') DEFAULT 'pending',
    payment_transaction_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES active_loans(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_transaction_id) REFERENCES transactions(id)
);

-- Tabel Notifikasi
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'warning', 'success', 'error') DEFAULT 'info',
    category ENUM('loan_approval', 'payment_reminder', 'account_activity', 'system') DEFAULT 'system',
    is_read BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(255),
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabel Pengaturan Sistem
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Tabel Log Aktivitas
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabel OTP untuk Verifikasi
CREATE TABLE otp_verifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    purpose ENUM('registration', 'login', 'password_reset', 'transaction') NOT NULL,
    phone_number VARCHAR(15) NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tabel Password Reset Tokens
CREATE TABLE password_reset_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    is_used BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ====================================================
-- INSERT DATA AWAL
-- ====================================================

-- Insert Divisi
INSERT INTO divisions (name, code) VALUES
('Human Resources', 'HRD'),
('Finance & Accounting', 'FIN'),
('Operations', 'OPS'),
('Logistics', 'LOG'),
('Information Technology', 'IT'),
('Marketing', 'MKT');

-- Insert Admin User
INSERT INTO users (nik, full_name, email, phone, password, role, division_id, position, employment_date, monthly_salary, status, is_verified) VALUES
('ADMIN001', 'Administrator System', 'admin@puninar.com', '081234567890', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 1, 'System Administrator', '2020-01-01', 15000000, 'active', 1),
('HRD001', 'Siti Nurhaliza', 'hrd@puninar.com', '081234567891', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 'HRD Manager', '2020-01-15', 12000000, 'active', 1),
('FIN001', 'Budi Santoso', 'finance@puninar.com', '081234567892', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 2, 'Finance Manager', '2020-02-01', 13000000, 'active', 1);

-- Insert Sample Karyawan
INSERT INTO users (nik, full_name, email, phone, password, role, division_id, position, employment_date, monthly_salary, status, is_verified) VALUES
('PUN001', 'Ahmad Fauzi', 'ahmad.fauzi@puninar.com', '081234567893', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'nasabah', 4, 'Driver', '2019-03-01', 5000000, 'active', 1),
('PUN002', 'Dewi Sartika', 'dewi.sartika@puninar.com', '081234567894', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'nasabah', 3, 'Supervisor Operasional', '2018-06-15', 8000000, 'active', 1),
('PUN003', 'Rizki Pratama', 'rizki.pratama@puninar.com', '081234567895', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'nasabah', 5, 'IT Support', '2021-01-10', 6500000, 'active', 1);

-- Insert Pengaturan Sistem
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('loan_interest_rate', '1.5', 'number', 'Suku bunga pinjaman per bulan (%)'),
('savings_interest_rate', '0', 'number', 'Bunga simpanan sukarela dinonaktifkan (ditiadakan RAT ke-19)'),
('max_loan_multiplier', '2', 'number', 'Plafon pinjaman standar (kelipatan simpanan wajib)'),
('max_loan_multiplier_limited', '1', 'number', 'Plafon pinjaman terbatas untuk outsourcing / tanpa potongan HRD'),
('max_loan_term', '18', 'number', 'Maksimal jangka waktu pinjaman termasuk kebijakan khusus (bulan)'),
('max_loan_term_standard', '12', 'number', 'Maksimal tenor standar tanpa surat pernyataan (bulan)'),
('min_mandatory_savings', '50000', 'number', 'Minimal simpanan wajib anggota (Rp)'),
('min_employment_months', '12', 'number', 'Minimal masa kerja untuk pinjaman (bulan)'),
('late_fee_percentage', '0.5', 'number', 'Denda keterlambatan per hari (%)'),
('company_name', 'Koperasi Konsumen Karyawan PT. Puninar Jaya (KOPPUJA)', 'string', 'Nama koperasi'),
('company_address', 'Cakung, Jakarta Timur', 'string', 'Alamat perusahaan'),
('session_timeout', '900', 'number', 'Timeout session dalam detik (15 menit)');

-- ====================================================
-- INDEXES UNTUK PERFORMA
-- ====================================================

-- Index untuk pencarian cepat
CREATE INDEX idx_users_nik ON users(nik);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role_status ON users(role, status);
CREATE INDEX idx_loan_applications_user_status ON loan_applications(user_id, status);
CREATE INDEX idx_active_loans_user_status ON active_loans(user_id, status);
CREATE INDEX idx_transactions_user_date ON transactions(user_id, transaction_date);
CREATE INDEX idx_loan_schedules_loan_status ON loan_schedules(loan_id, status);
CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);
CREATE INDEX idx_activity_logs_user_date ON activity_logs(user_id, created_at);
CREATE INDEX idx_password_reset_tokens_token ON password_reset_tokens(token);
CREATE INDEX idx_password_reset_tokens_user_expires ON password_reset_tokens(user_id, expires_at);

-- ====================================================
-- VIEWS UNTUK LAPORAN
-- ====================================================

-- View untuk Dashboard Nasabah
CREATE VIEW view_nasabah_dashboard AS
SELECT 
    u.id as user_id,
    u.nik,
    u.full_name,
    sa.balance as savings_balance,
    COALESCE(al.remaining_balance, 0) as loan_balance,
    al.next_due_date,
    al.monthly_installment,
    (SELECT COUNT(*) FROM notifications n WHERE n.user_id = u.id AND n.is_read = 0) as unread_notifications
FROM users u
LEFT JOIN savings_accounts sa ON u.id = sa.user_id AND sa.status = 'active'
LEFT JOIN active_loans al ON u.id = al.user_id AND al.status = 'active'
WHERE u.role = 'nasabah' AND u.status = 'active';

-- View untuk Dashboard Admin
CREATE VIEW view_admin_dashboard AS
SELECT 
    (SELECT COUNT(*) FROM loan_applications WHERE status = 'pending') as pending_applications,
    (SELECT COUNT(*) FROM active_loans WHERE status = 'active') as active_loans,
    (SELECT SUM(remaining_balance) FROM active_loans WHERE status = 'active') as total_outstanding,
    (SELECT SUM(balance) FROM savings_accounts WHERE status = 'active') as total_savings,
    (SELECT COUNT(*) FROM loan_schedules WHERE status = 'overdue') as overdue_payments;

-- View untuk Laporan Keuangan
CREATE VIEW view_financial_report AS
SELECT 
    DATE(t.transaction_date) as report_date,
    t.type,
    COUNT(*) as transaction_count,
    SUM(t.amount) as total_amount
FROM transactions t
WHERE t.status = 'completed'
GROUP BY DATE(t.transaction_date), t.type
ORDER BY report_date DESC;
