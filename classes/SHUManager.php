<?php
/**
 * Perhitungan dan pembagian SHU (Sisa Hasil Usaha)
 * Koperasi Konsumen Karyawan PT. Puninar Jaya (KOPPUJA)
 *
 * Rumus:
 *   Pendapatan  = bunga pinjaman terbayar + denda terbayar + pendapatan lain
 *   Beban       = biaya operasional
 *   SHU         = Pendapatan - Beban
 *
 * Pembagian SHU (persentase dapat diubah di Pengaturan):
 *   Cadangan, jasa anggota, dana pengurus, dana karyawan,
 *   dana pendidikan, dana sosial.
 *
 * Jasa anggota dibagi ke tiap anggota:
 *   Jasa simpanan = (saldo simpanan wajib anggota / total simpanan) x porsi jasa simpanan
 *   Jasa pinjaman = (bunga pinjaman yang dibayar anggota / total bunga) x porsi jasa pinjaman
 */

class SHUManager
{
    private $db;
    private $dbHelper;
    private $schemaReady = false;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->dbHelper = new DatabaseHelper();
        $this->ensureSchema();
    }

    public function ensureSchema()
    {
        if ($this->schemaReady) {
            return;
        }

        $pdo = $this->db->getConnection();

        $pdo->exec("CREATE TABLE IF NOT EXISTS shu_periods (
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
            status ENUM('draft','final') DEFAULT 'draft',
            generated_by INT NULL,
            generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_shu_year (year)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS shu_member_shares (
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
            INDEX idx_shu_share_user (user_id),
            CONSTRAINT fk_shu_share_period FOREIGN KEY (period_id) REFERENCES shu_periods(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $defaults = [
            ['shu_pct_cadangan', '25', 'number', 'Alokasi SHU untuk cadangan koperasi (%)'],
            ['shu_pct_jasa_anggota', '40', 'number', 'Alokasi SHU untuk jasa anggota (%)'],
            ['shu_pct_jasa_simpanan', '50', 'number', 'Porsi jasa simpanan dari jasa anggota (%)'],
            ['shu_pct_jasa_pinjaman', '50', 'number', 'Porsi jasa pinjaman dari jasa anggota (%)'],
            ['shu_pct_dana_pengurus', '10', 'number', 'Alokasi SHU untuk dana pengurus (%)'],
            ['shu_pct_dana_karyawan', '5', 'number', 'Alokasi SHU untuk dana karyawan (%)'],
            ['shu_pct_dana_pendidikan', '10', 'number', 'Alokasi SHU untuk dana pendidikan (%)'],
            ['shu_pct_dana_sosial', '10', 'number', 'Alokasi SHU untuk dana sosial (%)'],
        ];

        foreach ($defaults as $row) {
            $exists = $this->db->selectOne(
                "SELECT id FROM system_settings WHERE setting_key = ?",
                [$row[0]]
            );
            if (!$exists) {
                $this->db->insert(
                    "INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)",
                    $row
                );
            }
        }

        $this->schemaReady = true;
    }

    public function getAllocationSettings()
    {
        $keys = [
            'cadangan' => ['shu_pct_cadangan', 25],
            'jasa_anggota' => ['shu_pct_jasa_anggota', 40],
            'jasa_simpanan' => ['shu_pct_jasa_simpanan', 50],
            'jasa_pinjaman' => ['shu_pct_jasa_pinjaman', 50],
            'dana_pengurus' => ['shu_pct_dana_pengurus', 10],
            'dana_karyawan' => ['shu_pct_dana_karyawan', 5],
            'dana_pendidikan' => ['shu_pct_dana_pendidikan', 10],
            'dana_sosial' => ['shu_pct_dana_sosial', 10],
        ];

        $settings = [];
        foreach ($keys as $name => $meta) {
            $val = $this->dbHelper->getSystemSetting($meta[0]);
            $settings[$name] = ($val === null || $val === '') ? (float)$meta[1] : (float)$val;
        }

        return $settings;
    }

    public function getMainAllocationTotal($settings = null)
    {
        $settings = $settings ?: $this->getAllocationSettings();
        return $settings['cadangan']
            + $settings['jasa_anggota']
            + $settings['dana_pengurus']
            + $settings['dana_karyawan']
            + $settings['dana_pendidikan']
            + $settings['dana_sosial'];
    }

    public function calculate($year, $operatingExpense = 0, $otherIncome = 0, $endDate = null)
    {
        $year = (int)$year;
        $startDate = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);
        $today = date('Y-m-d');
        $endDate = $endDate ?: (($year === (int)date('Y')) ? min($today, $yearEnd) : $yearEnd);
        if ($endDate < $startDate) {
            $endDate = $startDate;
        }

        $operatingExpense = max(0, (float)$operatingExpense);
        $otherIncome = max(0, (float)$otherIncome);
        $pct = $this->getAllocationSettings();

        $incomeRow = $this->db->selectOne(
            "SELECT
                COALESCE(SUM(
                    CASE
                        WHEN ls.status = 'paid' THEN ls.interest_amount
                        WHEN ls.status = 'partial' THEN LEAST(ls.paid_amount, ls.interest_amount)
                        ELSE 0
                    END
                ), 0) AS interest_income,
                COALESCE(SUM(ls.late_fee), 0) AS late_fee_income
             FROM loan_schedules ls
             WHERE ls.status IN ('paid', 'partial')
               AND ls.paid_date BETWEEN ? AND ?",
            [$startDate, $endDate]
        );

        $interestIncome = (float)($incomeRow['interest_income'] ?? 0);
        $lateFeeIncome = (float)($incomeRow['late_fee_income'] ?? 0);
        $totalIncome = $interestIncome + $lateFeeIncome + $otherIncome;
        $totalExpense = $operatingExpense;
        $shuAmount = $totalIncome - $totalExpense;

        $allocation = $this->allocateShu($shuAmount, $pct);

        $members = $this->buildMemberShares(
            $startDate,
            $endDate,
            $allocation['jasa_simpanan'],
            $allocation['jasa_pinjaman']
        );

        $saved = $this->getSavedPeriod($year);

        return [
            'year' => $year,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'operating_expense' => $operatingExpense,
            'other_income' => $otherIncome,
            'interest_income' => $interestIncome,
            'late_fee_income' => $lateFeeIncome,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'shu_amount' => $shuAmount,
            'percentages' => $pct,
            'allocation' => $allocation,
            'members' => $members['rows'],
            'total_savings_base' => $members['total_savings'],
            'total_interest_base' => $members['total_interest'],
            'member_count' => count($members['rows']),
            'member_shu_total' => $members['distributed'],
            'saved' => $saved,
            'is_current_year' => $year === (int)date('Y'),
        ];
    }

    public function getSavedPeriod($year)
    {
        return $this->db->selectOne(
            "SELECT p.*, u.full_name AS generated_by_name
             FROM shu_periods p
             LEFT JOIN users u ON u.id = p.generated_by
             WHERE p.year = ?",
            [(int)$year]
        );
    }

    public function getSavedShares($periodId)
    {
        return $this->db->select(
            "SELECT s.*, u.nik, u.full_name, d.name AS division_name
             FROM shu_member_shares s
             JOIN users u ON u.id = s.user_id
             LEFT JOIN divisions d ON d.id = u.division_id
             WHERE s.period_id = ?
             ORDER BY s.shu_total DESC, u.full_name ASC",
            [(int)$periodId]
        );
    }

    public function savePeriod($calc, $userId, $status = 'draft', $notes = '')
    {
        $existing = $this->getSavedPeriod($calc['year']);
        if ($existing && $existing['status'] === 'final' && $status !== 'final') {
            return ['success' => false, 'message' => 'Laporan SHU tahun ini sudah dikunci. Buka kunci dulu jika perlu dihitung ulang.'];
        }

        $pct = $calc['percentages'];
        $alloc = $calc['allocation'];

        $fields = [
            'year' => $calc['year'],
            'start_date' => $calc['start_date'],
            'end_date' => $calc['end_date'],
            'operating_expense' => $calc['operating_expense'],
            'other_income' => $calc['other_income'],
            'interest_income' => $calc['interest_income'],
            'late_fee_income' => $calc['late_fee_income'],
            'total_income' => $calc['total_income'],
            'total_expense' => $calc['total_expense'],
            'shu_amount' => $calc['shu_amount'],
            'pct_cadangan' => $pct['cadangan'],
            'pct_jasa_anggota' => $pct['jasa_anggota'],
            'pct_jasa_simpanan' => $pct['jasa_simpanan'],
            'pct_jasa_pinjaman' => $pct['jasa_pinjaman'],
            'pct_dana_pengurus' => $pct['dana_pengurus'],
            'pct_dana_karyawan' => $pct['dana_karyawan'],
            'pct_dana_pendidikan' => $pct['dana_pendidikan'],
            'pct_dana_sosial' => $pct['dana_sosial'],
            'amt_cadangan' => $alloc['cadangan'],
            'amt_jasa_anggota' => $alloc['jasa_anggota'],
            'amt_jasa_simpanan' => $alloc['jasa_simpanan'],
            'amt_jasa_pinjaman' => $alloc['jasa_pinjaman'],
            'amt_dana_pengurus' => $alloc['dana_pengurus'],
            'amt_dana_karyawan' => $alloc['dana_karyawan'],
            'amt_dana_pendidikan' => $alloc['dana_pendidikan'],
            'amt_dana_sosial' => $alloc['dana_sosial'],
            'total_savings_base' => $calc['total_savings_base'],
            'total_interest_base' => $calc['total_interest_base'],
            'member_count' => $calc['member_count'],
            'notes' => $notes,
            'status' => $status,
            'generated_by' => $userId,
        ];

        try {
            $this->db->beginTransaction();

            if ($existing) {
                $periodId = (int)$existing['id'];
                $this->db->update(
                    "UPDATE shu_periods SET
                        start_date=?, end_date=?, operating_expense=?, other_income=?,
                        interest_income=?, late_fee_income=?, total_income=?, total_expense=?,
                        shu_amount=?, pct_cadangan=?, pct_jasa_anggota=?, pct_jasa_simpanan=?,
                        pct_jasa_pinjaman=?, pct_dana_pengurus=?, pct_dana_karyawan=?,
                        pct_dana_pendidikan=?, pct_dana_sosial=?, amt_cadangan=?, amt_jasa_anggota=?,
                        amt_jasa_simpanan=?, amt_jasa_pinjaman=?, amt_dana_pengurus=?,
                        amt_dana_karyawan=?, amt_dana_pendidikan=?, amt_dana_sosial=?,
                        total_savings_base=?, total_interest_base=?, member_count=?, notes=?,
                        status=?, generated_by=?
                     WHERE id=?",
                    [
                        $fields['start_date'], $fields['end_date'], $fields['operating_expense'], $fields['other_income'],
                        $fields['interest_income'], $fields['late_fee_income'], $fields['total_income'], $fields['total_expense'],
                        $fields['shu_amount'], $fields['pct_cadangan'], $fields['pct_jasa_anggota'], $fields['pct_jasa_simpanan'],
                        $fields['pct_jasa_pinjaman'], $fields['pct_dana_pengurus'], $fields['pct_dana_karyawan'],
                        $fields['pct_dana_pendidikan'], $fields['pct_dana_sosial'], $fields['amt_cadangan'], $fields['amt_jasa_anggota'],
                        $fields['amt_jasa_simpanan'], $fields['amt_jasa_pinjaman'], $fields['amt_dana_pengurus'],
                        $fields['amt_dana_karyawan'], $fields['amt_dana_pendidikan'], $fields['amt_dana_sosial'],
                        $fields['total_savings_base'], $fields['total_interest_base'], $fields['member_count'], $fields['notes'],
                        $fields['status'], $fields['generated_by'], $periodId
                    ]
                );
                $this->db->delete("DELETE FROM shu_member_shares WHERE period_id = ?", [$periodId]);
            } else {
                $periodId = $this->db->insert(
                    "INSERT INTO shu_periods (
                        year, start_date, end_date, operating_expense, other_income,
                        interest_income, late_fee_income, total_income, total_expense, shu_amount,
                        pct_cadangan, pct_jasa_anggota, pct_jasa_simpanan, pct_jasa_pinjaman,
                        pct_dana_pengurus, pct_dana_karyawan, pct_dana_pendidikan, pct_dana_sosial,
                        amt_cadangan, amt_jasa_anggota, amt_jasa_simpanan, amt_jasa_pinjaman,
                        amt_dana_pengurus, amt_dana_karyawan, amt_dana_pendidikan, amt_dana_sosial,
                        total_savings_base, total_interest_base, member_count, notes, status, generated_by
                    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    [
                        $fields['year'], $fields['start_date'], $fields['end_date'], $fields['operating_expense'], $fields['other_income'],
                        $fields['interest_income'], $fields['late_fee_income'], $fields['total_income'], $fields['total_expense'], $fields['shu_amount'],
                        $fields['pct_cadangan'], $fields['pct_jasa_anggota'], $fields['pct_jasa_simpanan'], $fields['pct_jasa_pinjaman'],
                        $fields['pct_dana_pengurus'], $fields['pct_dana_karyawan'], $fields['pct_dana_pendidikan'], $fields['pct_dana_sosial'],
                        $fields['amt_cadangan'], $fields['amt_jasa_anggota'], $fields['amt_jasa_simpanan'], $fields['amt_jasa_pinjaman'],
                        $fields['amt_dana_pengurus'], $fields['amt_dana_karyawan'], $fields['amt_dana_pendidikan'], $fields['amt_dana_sosial'],
                        $fields['total_savings_base'], $fields['total_interest_base'], $fields['member_count'], $fields['notes'], $fields['status'], $fields['generated_by']
                    ]
                );
            }

            foreach ($calc['members'] as $row) {
                $this->db->insert(
                    "INSERT INTO shu_member_shares (
                        period_id, user_id, savings_balance, interest_paid, late_fee_paid,
                        shu_simpanan, shu_pinjaman, shu_total
                    ) VALUES (?,?,?,?,?,?,?,?)",
                    [
                        $periodId, $row['user_id'], $row['savings_balance'], $row['interest_paid'], $row['late_fee_paid'],
                        $row['shu_simpanan'], $row['shu_pinjaman'], $row['shu_total']
                    ]
                );
            }

            $this->dbHelper->logActivity(
                $userId,
                $status === 'final' ? 'shu_finalized' : 'shu_saved',
                'shu_period',
                $periodId,
                null,
                ['year' => $calc['year'], 'shu_amount' => $calc['shu_amount']]
            );

            $this->db->commit();
            return ['success' => true, 'period_id' => $periodId, 'message' => $status === 'final' ? 'Laporan SHU dikunci sebagai final.' : 'Perhitungan SHU berhasil disimpan.'];
        } catch (Exception $e) {
            $this->db->rollback();
            error_log('SHU save error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Gagal menyimpan laporan SHU.'];
        }
    }

    public function reopenPeriod($year, $userId)
    {
        $existing = $this->getSavedPeriod($year);
        if (!$existing) {
            return ['success' => false, 'message' => 'Laporan SHU tahun ini belum ada.'];
        }

        $this->db->update(
            "UPDATE shu_periods SET status = 'draft' WHERE id = ?",
            [$existing['id']]
        );
        $this->dbHelper->logActivity($userId, 'shu_reopened', 'shu_period', $existing['id'], null, ['year' => (int)$year]);
        return ['success' => true, 'message' => 'Laporan SHU dibuka kembali sebagai draf.'];
    }

    public function getMemberShare($userId, $year)
    {
        $calc = $this->calculate($year);
        foreach ($calc['members'] as $row) {
            if ((int)$row['user_id'] === (int)$userId) {
                $row['period'] = $calc;
                $row['saved'] = $calc['saved'];
                return $row;
            }
        }

        return [
            'user_id' => (int)$userId,
            'savings_balance' => 0,
            'interest_paid' => 0,
            'late_fee_paid' => 0,
            'shu_simpanan' => 0,
            'shu_pinjaman' => 0,
            'shu_total' => 0,
            'period' => $calc,
            'saved' => $calc['saved'],
        ];
    }

    public function listPeriods()
    {
        return $this->db->select(
            "SELECT p.*, u.full_name AS generated_by_name
             FROM shu_periods p
             LEFT JOIN users u ON u.id = p.generated_by
             ORDER BY p.year DESC"
        );
    }

    private function allocateShu($shuAmount, $pct)
    {
        $positive = max(0, (float)$shuAmount);

        $cadangan = round($positive * $pct['cadangan'] / 100);
        $jasaAnggota = round($positive * $pct['jasa_anggota'] / 100);
        $danaPengurus = round($positive * $pct['dana_pengurus'] / 100);
        $danaKaryawan = round($positive * $pct['dana_karyawan'] / 100);
        $danaPendidikan = round($positive * $pct['dana_pendidikan'] / 100);
        $danaSosial = $positive - $cadangan - $jasaAnggota - $danaPengurus - $danaKaryawan - $danaPendidikan;

        $jasaSimpanan = round($jasaAnggota * $pct['jasa_simpanan'] / 100);
        $jasaPinjaman = $jasaAnggota - $jasaSimpanan;

        return [
            'cadangan' => $cadangan,
            'jasa_anggota' => $jasaAnggota,
            'jasa_simpanan' => $jasaSimpanan,
            'jasa_pinjaman' => $jasaPinjaman,
            'dana_pengurus' => $danaPengurus,
            'dana_karyawan' => $danaKaryawan,
            'dana_pendidikan' => $danaPendidikan,
            'dana_sosial' => $danaSosial,
        ];
    }

    private function buildMemberShares($startDate, $endDate, $poolSimpanan, $poolPinjaman)
    {
        $members = $this->db->select(
            "SELECT u.id AS user_id, u.nik, u.full_name, d.name AS division_name,
                    COALESCE(sa.balance, 0) AS savings_balance
             FROM users u
             LEFT JOIN divisions d ON d.id = u.division_id
             LEFT JOIN savings_accounts sa ON sa.user_id = u.id AND sa.status = 'active'
             WHERE u.role = 'nasabah' AND u.status IN ('active', 'resigned')
             ORDER BY u.full_name ASC"
        );

        $paid = $this->db->select(
            "SELECT al.user_id,
                    COALESCE(SUM(
                        CASE
                            WHEN ls.status = 'paid' THEN ls.interest_amount
                            WHEN ls.status = 'partial' THEN LEAST(ls.paid_amount, ls.interest_amount)
                            ELSE 0
                        END
                    ), 0) AS interest_paid,
                    COALESCE(SUM(ls.late_fee), 0) AS late_fee_paid
             FROM loan_schedules ls
             JOIN active_loans al ON al.id = ls.loan_id
             WHERE ls.status IN ('paid', 'partial')
               AND ls.paid_date BETWEEN ? AND ?
             GROUP BY al.user_id",
            [$startDate, $endDate]
        );

        $paidMap = [];
        foreach ($paid as $row) {
            $paidMap[(int)$row['user_id']] = $row;
        }

        $totalSavings = 0;
        $totalInterest = 0;
        foreach ($members as $m) {
            $uid = (int)$m['user_id'];
            $totalSavings += (float)$m['savings_balance'];
            $totalInterest += (float)($paidMap[$uid]['interest_paid'] ?? 0);
        }

        $rows = [];
        $sumSimpanan = 0;
        $sumPinjaman = 0;
        $eligible = [];

        foreach ($members as $m) {
            $uid = (int)$m['user_id'];
            $savings = (float)$m['savings_balance'];
            $interestPaid = (float)($paidMap[$uid]['interest_paid'] ?? 0);
            $lateFeePaid = (float)($paidMap[$uid]['late_fee_paid'] ?? 0);

            if ($savings <= 0 && $interestPaid <= 0 && $lateFeePaid <= 0) {
                continue;
            }

            $shuSimpanan = ($totalSavings > 0 && $poolSimpanan > 0)
                ? floor($savings / $totalSavings * $poolSimpanan)
                : 0;
            $shuPinjaman = ($totalInterest > 0 && $poolPinjaman > 0)
                ? floor($interestPaid / $totalInterest * $poolPinjaman)
                : 0;

            $eligible[] = [
                'user_id' => $uid,
                'nik' => $m['nik'],
                'full_name' => $m['full_name'],
                'division_name' => $m['division_name'],
                'savings_balance' => $savings,
                'interest_paid' => $interestPaid,
                'late_fee_paid' => $lateFeePaid,
                'shu_simpanan' => $shuSimpanan,
                'shu_pinjaman' => $shuPinjaman,
                'shu_total' => $shuSimpanan + $shuPinjaman,
            ];
            $sumSimpanan += $shuSimpanan;
            $sumPinjaman += $shuPinjaman;
        }

        if (!empty($eligible)) {
            $diffSimpanan = $poolSimpanan - $sumSimpanan;
            $diffPinjaman = $poolPinjaman - $sumPinjaman;
            $last = count($eligible) - 1;
            if ($totalSavings > 0) {
                $eligible[$last]['shu_simpanan'] += $diffSimpanan;
            }
            if ($totalInterest > 0) {
                $eligible[$last]['shu_pinjaman'] += $diffPinjaman;
            }
            $eligible[$last]['shu_total'] = $eligible[$last]['shu_simpanan'] + $eligible[$last]['shu_pinjaman'];
        }

        usort($eligible, function ($a, $b) {
            if ($a['shu_total'] == $b['shu_total']) {
                return strcmp($a['full_name'], $b['full_name']);
            }
            return ($a['shu_total'] < $b['shu_total']) ? 1 : -1;
        });

        $distributed = 0;
        foreach ($eligible as $row) {
            $distributed += $row['shu_total'];
        }

        return [
            'rows' => $eligible,
            'total_savings' => $totalSavings,
            'total_interest' => $totalInterest,
            'distributed' => $distributed,
        ];
    }
}
