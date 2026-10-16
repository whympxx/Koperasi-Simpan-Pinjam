-- ====================================================
-- PENYESUAIAN ATURAN KOPPUJA - RAT TAHUN BUKU 2025
-- Koperasi Konsumen Karyawan PT. Puninar Jaya
-- ====================================================
-- Ringkasan kebijakan yang diterapkan:
-- 1. Plafon pinjaman = kelipatan Simpanan Wajib (bukan gaji)
--    - 2x bila potongan lewat HRD / Yayasan membantu
--    - 1x bila Outsourcing / angsuran mandiri (tanpa potongan HRD)
-- 2. Tenor standar maks. 12 bulan; kebijakan khusus maks. 18 bulan
-- 3. Simpanan Wajib minimal Rp 50.000 (beku selama masih anggota)
-- 4. Simpanan Sukarela telah ditiadakan (RAT ke-19)
-- ====================================================

USE simpanpinjam_puninar;

-- Kolom kategori anggota & status potongan gaji
SET @col_member := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'member_category'
);
SET @sql_member := IF(@col_member = 0,
    "ALTER TABLE users ADD COLUMN member_category ENUM('karyawan','driver','outsourcing') NOT NULL DEFAULT 'karyawan' AFTER position",
    "SELECT 'member_category already exists'");
PREPARE stmt FROM @sql_member; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_payroll := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'payroll_deduction'
);
SET @sql_payroll := IF(@col_payroll = 0,
    "ALTER TABLE users ADD COLUMN payroll_deduction TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=potongan HRD/Yayasan, 0=angsur mandiri' AFTER member_category",
    "SELECT 'payroll_deduction already exists'");
PREPARE stmt FROM @sql_payroll; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Samakan driver dari position yang mengandung 'driver'/'supir'
UPDATE users
SET member_category = 'driver'
WHERE role = 'nasabah'
  AND (
      LOWER(IFNULL(position, '')) LIKE '%driver%'
      OR LOWER(IFNULL(position, '')) LIKE '%supir%'
      OR LOWER(IFNULL(position, '')) LIKE '%pengemudi%'
  );

-- Pengaturan sistem sesuai RAT 2025
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('max_loan_multiplier', '2', 'number', 'Plafon pinjaman standar (kelipatan simpanan wajib) - karyawan/driver dengan potongan HRD'),
('max_loan_multiplier_limited', '1', 'number', 'Plafon pinjaman terbatas (kelipatan simpanan wajib) - outsourcing / tanpa potongan HRD'),
('max_loan_term', '18', 'number', 'Maksimal tenor pinjaman absolut termasuk kebijakan khusus (bulan)'),
('max_loan_term_standard', '12', 'number', 'Maksimal tenor pinjaman standar tanpa surat pernyataan (bulan)'),
('min_mandatory_savings', '50000', 'number', 'Minimal simpanan wajib anggota (Rp)'),
('min_savings_balance', '50000', 'number', 'Saldo minimal simpanan wajib yang harus mengendap (Rp)'),
('savings_interest_rate', '0', 'number', 'Bunga simpanan sukarela dinonaktifkan (sudah ditiadakan RAT ke-19)'),
('voluntary_savings_enabled', '0', 'boolean', 'Pelayanan Simpanan Sukarela (0=ditiadakan sesuai RAT ke-19)'),
('company_name', 'Koperasi Konsumen Karyawan PT. Puninar Jaya (KOPPUJA)', 'string', 'Nama koperasi')
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    setting_type = VALUES(setting_type),
    description = VALUES(description);

-- ====================================================
-- Perbarui basis aturan (jika tabel rules sudah ada)
-- ====================================================

UPDATE rules SET
    name = 'Jumlah pinjaman melebihi plafon simpanan wajib',
    description = 'JIKA jumlah pinjaman melebihi plafon (1x atau 2x simpanan wajib sesuai kategori anggota) MAKA pengajuan ditolak. Sesuai ketentuan KOPPUJA RAT 2025.',
    message = 'Jumlah pinjaman melebihi plafon maksimal Rp {nilai} (kelipatan simpanan wajib sesuai status potongan gaji Anda).',
    is_active = 1
WHERE rule_code = 'R05';

UPDATE rule_conditions rc
JOIN rules r ON r.id = rc.rule_id
SET rc.fact_key = 'jumlah_pinjaman',
    rc.operator = '>',
    rc.value_type = 'fact',
    rc.compare_value = 'plafon_maksimal',
    rc.fallback_value = '0'
WHERE r.rule_code = 'R05';

UPDATE rules SET
    name = 'Tenor melebihi batas maksimal kebijakan',
    description = 'JIKA tenor > 18 bulan MAKA ditolak. Maksimal kebijakan khusus KOPPUJA adalah 18 bulan.',
    message = 'Jangka waktu pinjaman maksimal {nilai} bulan (kebijakan khusus dengan surat pernyataan).',
    is_active = 1
WHERE rule_code = 'R06';

UPDATE rule_conditions rc
JOIN rules r ON r.id = rc.rule_id
SET rc.fact_key = 'tenor_bulan',
    rc.operator = '>',
    rc.value_type = 'setting',
    rc.compare_value = 'max_loan_term',
    rc.fallback_value = '18'
WHERE r.rule_code = 'R06';

-- Aturan baru: simpanan wajib di bawah minimal
INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, is_system)
SELECT 'R08', 'loan_eligibility', 'Simpanan wajib di bawah minimal',
       'JIKA simpanan wajib kurang dari batas minimal MAKA pengajuan ditolak.',
       'reject', 'Simpanan wajib minimal Rp {nilai}. Saldo simpanan wajib Anda: Rp {fakta}.', 45, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM rules WHERE rule_code = 'R08');

INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, fallback_value, sort_order)
SELECT r.id, 'simpanan_wajib', '<', 'setting', 'min_mandatory_savings', '50000', 1
FROM rules r
WHERE r.rule_code = 'R08'
  AND NOT EXISTS (SELECT 1 FROM rule_conditions WHERE rule_id = r.id);

-- Aturan keputusan: tenor di atas standar (13-18) wajib review manual
INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, is_system)
SELECT 'R15', 'loan_decision', 'Tenor di atas standar (butuh surat pernyataan)',
       'JIKA tenor > 12 bulan MAKA review manual. Kebijakan 18 bulan memerlukan surat pernyataan bermaterai diketahui Kepala Unit.',
       'manual_review', 'Tenor di atas {nilai} bulan memerlukan surat pernyataan bermaterai dan review pengurus.', 25, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM rules WHERE rule_code = 'R15');

INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, fallback_value, sort_order)
SELECT r.id, 'tenor_bulan', '>', 'setting', 'max_loan_term_standard', '12', 1
FROM rules r
WHERE r.rule_code = 'R15'
  AND NOT EXISTS (SELECT 1 FROM rule_conditions WHERE rule_id = r.id);

-- Outsourcing / tanpa potongan HRD: selalu review manual (cek kemampuan bayar)
INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, is_system)
SELECT 'R16', 'loan_decision', 'Anggota tanpa potongan HRD / outsourcing',
       'JIKA anggota outsourcing atau tidak melalui potongan HRD MAKA review manual.',
       'manual_review', 'Anggota outsourcing / angsuran mandiri memerlukan verifikasi kemampuan bayar oleh pengurus.', 22, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM rules WHERE rule_code = 'R16');

INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, sort_order)
SELECT r.id, 'potongan_hrd', '=', 'static', '0', 1
FROM rules r
WHERE r.rule_code = 'R16'
  AND NOT EXISTS (SELECT 1 FROM rule_conditions WHERE rule_id = r.id);
