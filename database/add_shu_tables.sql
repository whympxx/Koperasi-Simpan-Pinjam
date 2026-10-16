-- ====================================================
-- PERHITUNGAN & LAPORAN SHU (Sisa Hasil Usaha)
-- Koperasi Konsumen Karyawan PT. Puninar Jaya (KOPPUJA)
-- ====================================================

USE simpanpinjam_puninar;

CREATE TABLE IF NOT EXISTS shu_periods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    year INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    operating_expense DECIMAL(15,2) DEFAULT 0.00,
    other_income DECIMAL(15,2) DEFAULT 0.00,
    interest_income DECIMAL(15,2) DEFAULT 0.00,
    late_fee_income DECIMAL(15,2) DEFAULT 0.00,
    total_income DECIMAL(15,2) DEFAULT 0.00,
    total_expense DECIMAL(15,2) DEFAULT 0.00,
    shu_amount DECIMAL(15,2) DEFAULT 0.00,
    pct_cadangan DECIMAL(5,2) DEFAULT 25.00,
    pct_jasa_anggota DECIMAL(5,2) DEFAULT 40.00,
    pct_jasa_simpanan DECIMAL(5,2) DEFAULT 50.00,
    pct_jasa_pinjaman DECIMAL(5,2) DEFAULT 50.00,
    pct_dana_pengurus DECIMAL(5,2) DEFAULT 10.00,
    pct_dana_karyawan DECIMAL(5,2) DEFAULT 5.00,
    pct_dana_pendidikan DECIMAL(5,2) DEFAULT 10.00,
    pct_dana_sosial DECIMAL(5,2) DEFAULT 10.00,
    amt_cadangan DECIMAL(15,2) DEFAULT 0.00,
    amt_jasa_anggota DECIMAL(15,2) DEFAULT 0.00,
    amt_jasa_simpanan DECIMAL(15,2) DEFAULT 0.00,
    amt_jasa_pinjaman DECIMAL(15,2) DEFAULT 0.00,
    amt_dana_pengurus DECIMAL(15,2) DEFAULT 0.00,
    amt_dana_karyawan DECIMAL(15,2) DEFAULT 0.00,
    amt_dana_pendidikan DECIMAL(15,2) DEFAULT 0.00,
    amt_dana_sosial DECIMAL(15,2) DEFAULT 0.00,
    total_savings_base DECIMAL(15,2) DEFAULT 0.00,
    total_interest_base DECIMAL(15,2) DEFAULT 0.00,
    member_count INT DEFAULT 0,
    notes TEXT,
    status ENUM('draft', 'final') DEFAULT 'draft',
    generated_by INT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_shu_year (year),
    FOREIGN KEY (generated_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS shu_member_shares (
    id INT PRIMARY KEY AUTO_INCREMENT,
    period_id INT NOT NULL,
    user_id INT NOT NULL,
    savings_balance DECIMAL(15,2) DEFAULT 0.00,
    interest_paid DECIMAL(15,2) DEFAULT 0.00,
    late_fee_paid DECIMAL(15,2) DEFAULT 0.00,
    shu_simpanan DECIMAL(15,2) DEFAULT 0.00,
    shu_pinjaman DECIMAL(15,2) DEFAULT 0.00,
    shu_total DECIMAL(15,2) DEFAULT 0.00,
    UNIQUE KEY uniq_period_user (period_id, user_id),
    FOREIGN KEY (period_id) REFERENCES shu_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('shu_pct_cadangan', '25', 'number', 'Alokasi SHU untuk cadangan koperasi (%)'),
('shu_pct_jasa_anggota', '40', 'number', 'Alokasi SHU untuk jasa anggota (%)'),
('shu_pct_jasa_simpanan', '50', 'number', 'Porsi jasa simpanan dari jasa anggota (%)'),
('shu_pct_jasa_pinjaman', '50', 'number', 'Porsi jasa pinjaman dari jasa anggota (%)'),
('shu_pct_dana_pengurus', '10', 'number', 'Alokasi SHU untuk dana pengurus (%)'),
('shu_pct_dana_karyawan', '5', 'number', 'Alokasi SHU untuk dana karyawan (%)'),
('shu_pct_dana_pendidikan', '10', 'number', 'Alokasi SHU untuk dana pendidikan (%)'),
('shu_pct_dana_sosial', '10', 'number', 'Alokasi SHU untuk dana sosial (%)')
ON DUPLICATE KEY UPDATE description = VALUES(description);
