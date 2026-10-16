-- ====================================================
-- BASIS PENGETAHUAN ATURAN (RULE-BASED / IF-THEN)
-- PT. Puninar Jaya Logistik - Simpan Pinjam System
--
-- Menyimpan aturan keputusan sebagai data sehingga admin dapat
-- menambah/mengubah/menonaktifkan aturan tanpa mengubah kode PHP.
-- ====================================================

USE simpanpinjam_puninar;

-- Tabel Aturan (bagian THEN dari setiap aturan)
CREATE TABLE IF NOT EXISTS rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rule_code VARCHAR(20) UNIQUE NOT NULL,
    rule_group ENUM('loan_eligibility', 'loan_decision') NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    conclusion ENUM('approve', 'reject', 'manual_review') NOT NULL,
    message TEXT NOT NULL,
    priority INT NOT NULL DEFAULT 100,
    is_active BOOLEAN DEFAULT TRUE,
    is_system BOOLEAN DEFAULT FALSE,
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Tabel Kondisi Aturan (bagian IF, seluruh kondisi digabung dengan AND)
CREATE TABLE IF NOT EXISTS rule_conditions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rule_id INT NOT NULL,
    fact_key VARCHAR(50) NOT NULL,
    operator ENUM('<', '<=', '=', '!=', '>=', '>', 'in', 'not_in') NOT NULL,
    value_type ENUM('static', 'setting', 'fact') NOT NULL DEFAULT 'static',
    compare_value VARCHAR(255) NOT NULL,
    fallback_value VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rule_id) REFERENCES rules(id) ON DELETE CASCADE
);

-- Tabel Jejak Penalaran (audit trail hasil inferensi)
CREATE TABLE IF NOT EXISTS rule_evaluation_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    rule_group VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    decision VARCHAR(30) NOT NULL,
    fired_rule_code VARCHAR(20) NULL,
    facts JSON,
    trace JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_rules_group_active ON rules(rule_group, is_active, priority);
CREATE INDEX IF NOT EXISTS idx_rule_conditions_rule ON rule_conditions(rule_id, sort_order);
CREATE INDEX IF NOT EXISTS idx_rule_eval_logs_entity ON rule_evaluation_logs(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_rule_eval_logs_user_date ON rule_evaluation_logs(user_id, created_at);

-- ====================================================
-- SEED ATURAN AWAL
-- Mencerminkan aturan yang sebelumnya tertanam di kode PHP.
-- ====================================================

-- ----- Kelompok: Kelayakan Pengajuan (loan_eligibility) -----

INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, is_system) VALUES
('R01', 'loan_eligibility', 'Masa kerja belum memenuhi syarat',
 'JIKA masa kerja kurang dari batas minimal MAKA pengajuan ditolak.',
 'reject', 'Masa kerja minimal {nilai} bulan. Masa kerja Anda: {fakta} bulan.', 10, TRUE, TRUE);
SET @r = LAST_INSERT_ID();
INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, fallback_value, sort_order) VALUES
(@r, 'masa_kerja_bulan', '<', 'setting', 'min_employment_months', '12', 1);

INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, is_system) VALUES
('R02', 'loan_eligibility', 'Masih memiliki pinjaman aktif',
 'JIKA nasabah masih memiliki pinjaman aktif MAKA pengajuan ditolak.',
 'reject', 'Anda sudah memiliki pinjaman aktif. Selesaikan pinjaman yang ada terlebih dahulu.', 20, TRUE, TRUE);
SET @r = LAST_INSERT_ID();
INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, sort_order) VALUES
(@r, 'punya_pinjaman_aktif', '=', 'static', '1', 1);

INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, is_system) VALUES
('R03', 'loan_eligibility', 'Masih ada pengajuan yang diproses',
 'JIKA nasabah memiliki pengajuan berstatus pending atau reviewing MAKA pengajuan baru ditolak.',
 'reject', 'Anda memiliki pengajuan pinjaman yang sedang diproses.', 30, TRUE, TRUE);
SET @r = LAST_INSERT_ID();
INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, sort_order) VALUES
(@r, 'punya_pengajuan_pending', '=', 'static', '1', 1);

INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, is_system) VALUES
('R04', 'loan_eligibility', 'Status kepegawaian tidak aktif',
 'JIKA status karyawan bukan active MAKA pengajuan ditolak.',
 'reject', 'Status kepegawaian Anda tidak aktif sehingga tidak dapat mengajukan pinjaman.', 40, TRUE, FALSE);
SET @r = LAST_INSERT_ID();
INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, sort_order) VALUES
(@r, 'status_karyawan', '!=', 'static', 'active', 1);

INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, is_system) VALUES
('R05', 'loan_eligibility', 'Jumlah pinjaman melebihi plafon simpanan wajib',
 'JIKA jumlah pinjaman melebihi plafon (1x/2x simpanan wajib) MAKA pengajuan ditolak. Sesuai KOPPUJA RAT 2025.',
 'reject', 'Jumlah pinjaman melebihi plafon maksimal Rp {nilai} (kelipatan simpanan wajib sesuai status potongan gaji Anda).', 50, TRUE, TRUE);
SET @r = LAST_INSERT_ID();
INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, fallback_value, sort_order) VALUES
(@r, 'jumlah_pinjaman', '>', 'fact', 'plafon_maksimal', '0', 1);

INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, is_system) VALUES
('R06', 'loan_eligibility', 'Tenor melebihi batas maksimal kebijakan',
 'JIKA tenor > 18 bulan MAKA ditolak. Maksimal kebijakan khusus KOPPUJA adalah 18 bulan.',
 'reject', 'Jangka waktu pinjaman maksimal {nilai} bulan (kebijakan khusus dengan surat pernyataan).', 60, TRUE, TRUE);
SET @r = LAST_INSERT_ID();
INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, fallback_value, sort_order) VALUES
(@r, 'tenor_bulan', '>', 'setting', 'max_loan_term', '18', 1);

INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, is_system) VALUES
('R07', 'loan_eligibility', 'Angsuran melebihi kemampuan bayar',
 'JIKA angsuran bulanan melebihi 40% gaji MAKA pengajuan ditolak. Nonaktif secara bawaan, aktifkan bila kebijakan ini diberlakukan.',
 'reject', 'Angsuran bulanan ({fakta}% dari gaji) melebihi batas maksimal {nilai}% dari gaji.', 70, FALSE, FALSE);
SET @r = LAST_INSERT_ID();
INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, sort_order) VALUES
(@r, 'rasio_angsuran_gaji', '>', 'static', '40', 1);

INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, is_system) VALUES
('R08', 'loan_eligibility', 'Simpanan wajib di bawah minimal',
 'JIKA simpanan wajib kurang dari batas minimal MAKA pengajuan ditolak.',
 'reject', 'Simpanan wajib minimal Rp {nilai}. Saldo simpanan wajib Anda: Rp {fakta}.', 45, TRUE, TRUE);
SET @r = LAST_INSERT_ID();
INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, fallback_value, sort_order) VALUES
(@r, 'simpanan_wajib', '<', 'setting', 'min_mandatory_savings', '50000', 1);

-- ----- Kelompok: Keputusan Persetujuan (loan_decision) -----

INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, is_system) VALUES
('R10', 'loan_decision', 'Persetujuan otomatis dinonaktifkan',
 'JIKA fitur auto-approve nonaktif MAKA seluruh pengajuan masuk review manual.',
 'manual_review', 'Persetujuan otomatis sedang dinonaktifkan, pengajuan diteruskan ke review admin.', 10, TRUE, TRUE);
SET @r = LAST_INSERT_ID();
INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, sort_order) VALUES
(@r, 'auto_approve_aktif', '=', 'static', '0', 1);

INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, is_system) VALUES
('R11', 'loan_decision', 'Jumlah pinjaman di atas batas auto-approve',
 'JIKA jumlah pinjaman melebihi batas auto-approve MAKA pengajuan direview manual.',
 'manual_review', 'Jumlah pinjaman melebihi batas persetujuan otomatis (Rp {nilai}).', 20, TRUE, TRUE);
SET @r = LAST_INSERT_ID();
INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, fallback_value, sort_order) VALUES
(@r, 'jumlah_pinjaman', '>', 'setting', 'loan_auto_approve_max_amount', '5000000', 1);

INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, is_system) VALUES
('R12', 'loan_decision', 'Tenor di atas batas auto-approve',
 'JIKA tenor melebihi batas auto-approve MAKA pengajuan direview manual.',
 'manual_review', 'Tenor pinjaman melebihi batas persetujuan otomatis ({nilai} bulan).', 30, TRUE, TRUE);
SET @r = LAST_INSERT_ID();
INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, fallback_value, sort_order) VALUES
(@r, 'tenor_bulan', '>', 'setting', 'loan_auto_approve_max_term_months', '12', 1);

INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, is_system) VALUES
('R15', 'loan_decision', 'Tenor di atas standar (butuh surat pernyataan)',
 'JIKA tenor > 12 bulan MAKA review manual. Kebijakan 18 bulan memerlukan surat pernyataan bermaterai.',
 'manual_review', 'Tenor di atas {nilai} bulan memerlukan surat pernyataan bermaterai dan review pengurus.', 25, TRUE, TRUE);
SET @r = LAST_INSERT_ID();
INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, fallback_value, sort_order) VALUES
(@r, 'tenor_bulan', '>', 'setting', 'max_loan_term_standard', '12', 1);

INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, is_system) VALUES
('R16', 'loan_decision', 'Anggota tanpa potongan HRD / outsourcing',
 'JIKA anggota outsourcing atau tidak melalui potongan HRD MAKA review manual.',
 'manual_review', 'Anggota outsourcing / angsuran mandiri memerlukan verifikasi kemampuan bayar oleh pengurus.', 22, TRUE, TRUE);
SET @r = LAST_INSERT_ID();
INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, sort_order) VALUES
(@r, 'potongan_hrd', '=', 'static', '0', 1);

INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, is_system) VALUES
('R13', 'loan_decision', 'Memiliki riwayat tunggakan',
 'JIKA nasabah pernah menunggak angsuran 3 kali atau lebih MAKA pengajuan direview manual.',
 'manual_review', 'Nasabah memiliki riwayat keterlambatan angsuran sebanyak {fakta} kali.', 40, TRUE, FALSE);
SET @r = LAST_INSERT_ID();
INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, sort_order) VALUES
(@r, 'riwayat_tunggakan', '>=', 'static', '3', 1);

INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, is_system) VALUES
('R14', 'loan_decision', 'Memenuhi seluruh kriteria auto-approve',
 'Aturan penutup: JIKA tidak ada aturan review manual yang terpenuhi MAKA pengajuan disetujui otomatis.',
 'approve', 'Disetujui otomatis oleh sistem (memenuhi kriteria auto-approve).', 999, TRUE, TRUE);
SET @r = LAST_INSERT_ID();
INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, sort_order) VALUES
(@r, 'jumlah_pinjaman', '>=', 'static', '0', 1);
