<?php
class RuleEngine {
    const GROUP_ELIGIBILITY = 'loan_eligibility';
    const GROUP_DECISION = 'loan_decision';

    const STATUS_FIRED = 'fired';
    const STATUS_NOT_FIRED = 'not_fired';
    const STATUS_SKIPPED = 'skipped';

    private $db;
    private $dbHelper;
    private static $ready = null;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->dbHelper = new DatabaseHelper();
    }

    /**
     * Daftar fakta yang dikenali engine beserta labelnya untuk UI.
     */
    public static function factCatalog() {
        return [
            'masa_kerja_bulan'        => ['label' => 'Masa kerja (bulan)', 'type' => 'number'],
            'status_karyawan'         => ['label' => 'Status kepegawaian', 'type' => 'text'],
            'kategori_anggota'        => ['label' => 'Kategori anggota (karyawan/driver/outsourcing)', 'type' => 'text'],
            'potongan_hrd'            => ['label' => 'Angsuran via potongan HRD (1/0)', 'type' => 'boolean'],
            'gaji_bulanan'            => ['label' => 'Gaji/komisi bulanan (Rp)', 'type' => 'number'],
            'simpanan_wajib'          => ['label' => 'Saldo simpanan wajib (Rp)', 'type' => 'number'],
            'batas_kelipatan'         => ['label' => 'Batas kelipatan plafon (1 atau 2)', 'type' => 'number'],
            'plafon_maksimal'         => ['label' => 'Plafon pinjaman maksimal (Rp)', 'type' => 'number'],
            'kelipatan_simpanan'      => ['label' => 'Kelipatan simpanan wajib (pinjaman / simpanan)', 'type' => 'number'],
            'punya_pinjaman_aktif'    => ['label' => 'Punya pinjaman aktif (1/0)', 'type' => 'boolean'],
            'punya_pengajuan_pending' => ['label' => 'Punya pengajuan diproses (1/0)', 'type' => 'boolean'],
            'riwayat_tunggakan'       => ['label' => 'Jumlah angsuran pernah terlambat', 'type' => 'number'],
            'pinjaman_lunas'          => ['label' => 'Jumlah pinjaman yang sudah lunas', 'type' => 'number'],
            'jumlah_pinjaman'         => ['label' => 'Jumlah pinjaman diajukan (Rp)', 'type' => 'number'],
            'tenor_bulan'             => ['label' => 'Tenor pinjaman (bulan)', 'type' => 'number'],
            'angsuran_bulanan'        => ['label' => 'Angsuran bulanan (Rp)', 'type' => 'number'],
            'kelipatan_gaji'          => ['label' => 'Kelipatan gaji (pinjaman / gaji)', 'type' => 'number'],
            'rasio_angsuran_gaji'     => ['label' => 'Rasio angsuran terhadap gaji (%)', 'type' => 'number'],
            'auto_approve_aktif'      => ['label' => 'Fitur auto-approve aktif (1/0)', 'type' => 'boolean'],
        ];
    }

    public static function operatorLabels() {
        return [
            '<'      => 'kurang dari',
            '<='     => 'kurang dari atau sama dengan',
            '='      => 'sama dengan',
            '!='     => 'tidak sama dengan',
            '>='     => 'lebih dari atau sama dengan',
            '>'      => 'lebih dari',
            'in'     => 'termasuk salah satu dari',
            'not_in' => 'tidak termasuk dari',
        ];
    }

    public static function groupLabels() {
        return [
            self::GROUP_ELIGIBILITY => 'Kelayakan Pengajuan Pinjaman',
            self::GROUP_DECISION    => 'Keputusan Persetujuan Pinjaman',
        ];
    }

    public static function conclusionLabels() {
        return [
            'approve'       => 'Setujui otomatis',
            'reject'        => 'Tolak pengajuan',
            'manual_review' => 'Review manual admin',
        ];
    }

    public static function factLabel($key) {
        $catalog = self::factCatalog();
        return $catalog[$key]['label'] ?? $key;
    }

    /**
     * Cek ketersediaan tabel basis pengetahuan (agar aplikasi tetap jalan
     * bila migrasi database belum dijalankan).
     */
    public function isReady() {
        if (self::$ready === null) {
            try {
                $row = $this->db->selectOne(
                    "SELECT COUNT(*) AS total FROM information_schema.tables 
                     WHERE table_schema = DATABASE() AND table_name IN ('rules', 'rule_conditions')"
                );
                self::$ready = ((int)($row['total'] ?? 0) >= 2);
            } catch (Exception $e) {
                self::$ready = false;
            }
        }
        return self::$ready;
    }

    /**
     * Apakah ada aturan aktif untuk sebuah kelompok.
     */
    public function hasActiveRules($group) {
        if (!$this->isReady()) {
            return false;
        }

        $row = $this->db->selectOne(
            "SELECT COUNT(*) AS total FROM rules WHERE rule_group = ? AND is_active = 1",
            [$group]
        );

        return (int)($row['total'] ?? 0) > 0;
    }

    /**
     * Ambil aturan beserta kondisinya.
     */
    public function getRules($group = null, $activeOnly = false) {
        if (!$this->isReady()) {
            return [];
        }

        $where = [];
        $params = [];

        if ($group) {
            $where[] = 'rule_group = ?';
            $params[] = $group;
        }
        if ($activeOnly) {
            $where[] = 'is_active = 1';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $rules = $this->db->select(
            "SELECT * FROM rules $whereClause ORDER BY rule_group ASC, priority ASC, id ASC",
            $params
        );

        if (empty($rules)) {
            return [];
        }

        $ids = array_column($rules, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $conditions = $this->db->select(
            "SELECT * FROM rule_conditions WHERE rule_id IN ($placeholders) ORDER BY sort_order ASC, id ASC",
            $ids
        );

        $grouped = [];
        foreach ($conditions as $condition) {
            $grouped[$condition['rule_id']][] = $condition;
        }

        foreach ($rules as &$rule) {
            $rule['conditions'] = $grouped[$rule['id']] ?? [];
        }
        unset($rule);

        return $rules;
    }

    public function getRule($id) {
        if (!$this->isReady()) {
            return null;
        }

        $rule = $this->db->selectOne("SELECT * FROM rules WHERE id = ?", [$id]);
        if (!$rule) {
            return null;
        }

        $rule['conditions'] = $this->db->select(
            "SELECT * FROM rule_conditions WHERE rule_id = ? ORDER BY sort_order ASC, id ASC",
            [$id]
        );

        return $rule;
    }

    /**
     * Jalankan inferensi terhadap sekumpulan fakta.
     *
     * @param string $group           Kelompok aturan
     * @param array  $facts           Pasangan fakta => nilai
     * @param string $defaultDecision Kesimpulan bila tidak ada aturan yang terpenuhi
     * @param string $defaultMessage  Pesan bawaan
     */
    public function evaluate($group, array $facts, $defaultDecision = 'no_match', $defaultMessage = '') {
        $facts = $this->normalizeFacts($facts);

        $result = [
            'group'          => $group,
            'decision'       => $defaultDecision,
            'rule_code'      => null,
            'rule_name'      => null,
            'message'        => $defaultMessage,
            'facts'          => $facts,
            'fired'          => [],
            'trace'          => [],
            'rules_total'    => 0,
            'source'         => 'rule_engine',
        ];

        $rules = $this->getRules($group, true);
        $result['rules_total'] = count($rules);

        foreach ($rules as $rule) {
            $evaluation = $this->evaluateRule($rule, $facts);
            $result['trace'][] = $evaluation;

            if ($evaluation['status'] !== self::STATUS_FIRED) {
                continue;
            }

            $result['fired'][] = $rule['rule_code'];

            // Aturan pertama yang terpenuhi (prioritas terkecil) menentukan kesimpulan.
            if ($result['rule_code'] === null) {
                $result['decision']  = $rule['conclusion'];
                $result['rule_code'] = $rule['rule_code'];
                $result['rule_name'] = $rule['name'];
                $result['message']   = $evaluation['message'];
            }
        }

        return $result;
    }

    /**
     * Evaluasi satu aturan: seluruh kondisi harus terpenuhi (AND).
     */
    private function evaluateRule($rule, array $facts) {
        $conditionResults = [];
        $hasFalse = false;
        $hasSkipped = false;

        foreach ($rule['conditions'] as $condition) {
            $evaluated = $this->evaluateCondition($condition, $facts);
            $conditionResults[] = $evaluated;

            if ($evaluated['status'] === self::STATUS_SKIPPED) {
                $hasSkipped = true;
            } elseif ($evaluated['status'] === self::STATUS_NOT_FIRED) {
                $hasFalse = true;
            }
        }

        if (empty($conditionResults)) {
            $status = self::STATUS_SKIPPED;
        } elseif ($hasFalse) {
            $status = self::STATUS_NOT_FIRED;
        } elseif ($hasSkipped) {
            $status = self::STATUS_SKIPPED;
        } else {
            $status = self::STATUS_FIRED;
        }

        $first = $conditionResults[0] ?? ['expected' => '', 'actual' => ''];

        return [
            'rule_code'  => $rule['rule_code'],
            'rule_name'  => $rule['name'],
            'conclusion' => $rule['conclusion'],
            'priority'   => (int)$rule['priority'],
            'status'     => $status,
            'if_text'    => $this->describeRuleConditions($rule),
            'conditions' => $conditionResults,
            'message'    => $this->renderMessage($rule['message'], $first),
        ];
    }

    /**
     * Evaluasi satu kondisi terhadap fakta.
     */
    private function evaluateCondition($condition, array $facts) {
        $factKey = $condition['fact_key'];
        $expected = $this->resolveComparisonValue($condition, $facts);

        $line = [
            'fact_key'   => $factKey,
            'fact_label' => self::factLabel($factKey),
            'operator'   => $condition['operator'],
            'expected'   => $expected,
            'actual'     => null,
            'status'     => self::STATUS_SKIPPED,
            'note'       => '',
        ];

        if (!array_key_exists($factKey, $facts) || $facts[$factKey] === null) {
            $line['note'] = 'Fakta tidak tersedia pada konteks ini';
            return $line;
        }

        if ($expected === null) {
            $line['note'] = 'Nilai pembanding tidak ditemukan';
            return $line;
        }

        $line['actual'] = $facts[$factKey];
        $line['status'] = $this->compare($facts[$factKey], $condition['operator'], $expected)
            ? self::STATUS_FIRED
            : self::STATUS_NOT_FIRED;

        return $line;
    }

    /**
     * Nilai pembanding bisa berasal dari nilai tetap, pengaturan sistem, atau fakta lain.
     */
    private function resolveComparisonValue($condition, array $facts) {
        $type = $condition['value_type'] ?? 'static';
        $value = $condition['compare_value'];
        $fallback = $condition['fallback_value'] ?? null;

        if ($type === 'setting') {
            $setting = $this->dbHelper->getSystemSetting($value);
            if ($setting === null || $setting === '') {
                return $fallback;
            }
            return $this->normalizeValue($setting);
        }

        if ($type === 'fact') {
            if (!array_key_exists($value, $facts) || $facts[$value] === null) {
                return $fallback;
            }
            return $facts[$value];
        }

        return $value;
    }

    private function compare($actual, $operator, $expected) {
        $actual = $this->normalizeValue($actual);
        $expected = $this->normalizeValue($expected);

        if ($operator === 'in' || $operator === 'not_in') {
            $list = array_map('trim', explode(',', (string)$expected));
            $found = false;
            foreach ($list as $item) {
                if ($this->compare($actual, '=', $item)) {
                    $found = true;
                    break;
                }
            }
            return $operator === 'in' ? $found : !$found;
        }

        $isNumeric = is_numeric($actual) && is_numeric($expected);
        if ($isNumeric) {
            $left = (float)$actual;
            $right = (float)$expected;
        } else {
            $left = mb_strtolower(trim((string)$actual));
            $right = mb_strtolower(trim((string)$expected));
        }

        switch ($operator) {
            case '<':
                return $left < $right;
            case '<=':
                return $left <= $right;
            case '>':
                return $left > $right;
            case '>=':
                return $left >= $right;
            case '=':
                return $isNumeric ? (abs($left - $right) < 0.000001) : ($left === $right);
            case '!=':
                return $isNumeric ? (abs($left - $right) >= 0.000001) : ($left !== $right);
            default:
                return false;
        }
    }

    private function normalizeValue($value) {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        return $value;
    }

    private function normalizeFacts(array $facts) {
        foreach ($facts as $key => $value) {
            $facts[$key] = $this->normalizeValue($value);
        }
        return $facts;
    }

    /**
     * Susun teks "JIKA ..." yang bisa dibaca manusia.
     */
    public function describeRuleConditions($rule) {
        $parts = [];

        foreach ($rule['conditions'] as $condition) {
            $value = $condition['compare_value'];
            if (($condition['value_type'] ?? 'static') === 'setting') {
                $value = 'pengaturan[' . $condition['compare_value'] . ']';
            } elseif (($condition['value_type'] ?? 'static') === 'fact') {
                $value = 'fakta[' . $condition['compare_value'] . ']';
            }

            $parts[] = self::factLabel($condition['fact_key']) . ' ' . $condition['operator'] . ' ' . $value;
        }

        return $parts ? implode(' DAN ', $parts) : '(tanpa kondisi)';
    }

    /**
     * Ganti placeholder {nilai} dan {fakta} pada pesan aturan.
     */
    private function renderMessage($message, $conditionResult) {
        $replacements = [
            '{nilai}' => $this->formatDisplayValue($conditionResult['expected'] ?? ''),
            '{fakta}' => $this->formatDisplayValue($conditionResult['actual'] ?? ''),
        ];

        return strtr($message, $replacements);
    }

    private function formatDisplayValue($value) {
        if (is_numeric($value)) {
            $number = (float)$value;
            $decimals = (floor($number) == $number) ? 0 : 2;
            return number_format($number, $decimals, ',', '.');
        }
        return (string)$value;
    }

    /**
     * Simpan jejak penalaran untuk keperluan audit dan laporan.
     */
    public function logEvaluation($result, $userId = null, $entityType = null, $entityId = null) {
        if (!$this->isReady()) {
            return false;
        }

        try {
            return $this->db->insert(
                "INSERT INTO rule_evaluation_logs 
                 (user_id, rule_group, entity_type, entity_id, decision, fired_rule_code, facts, trace)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId,
                    $result['group'],
                    $entityType,
                    $entityId,
                    $result['decision'],
                    $result['rule_code'],
                    json_encode($result['facts']),
                    json_encode($result['trace'])
                ]
            );
        } catch (Exception $e) {
            error_log('Rule evaluation log error: ' . $e->getMessage());
            return false;
        }
    }

    public function getEvaluationLogs($limit = 20, $group = null) {
        if (!$this->isReady()) {
            return [];
        }

        $where = '';
        $params = [];
        if ($group) {
            $where = 'WHERE rel.rule_group = ?';
            $params[] = $group;
        }

        // LIMIT di-inline karena koneksi memakai prepared statement asli (non-emulated).
        $limit = max(1, (int)$limit);

        return $this->db->select(
            "SELECT rel.*, u.full_name, u.nik
             FROM rule_evaluation_logs rel
             LEFT JOIN users u ON rel.user_id = u.id
             $where
             ORDER BY rel.created_at DESC
             LIMIT $limit",
            $params
        );
    }

    /**
     * Buat kode aturan berikutnya yang belum terpakai.
     */
    public function generateRuleCode() {
        $row = $this->db->selectOne(
            "SELECT MAX(CAST(SUBSTRING(rule_code, 2) AS UNSIGNED)) AS max_code 
             FROM rules WHERE rule_code REGEXP '^R[0-9]+$'"
        );

        $next = (int)($row['max_code'] ?? 0) + 1;
        return 'R' . str_pad($next, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Simpan aturan baru atau perbarui aturan yang ada beserta kondisinya.
     *
     * @param array $data       Data aturan (rule_code, rule_group, name, conclusion, message, ...)
     * @param array $conditions Daftar kondisi
     */
    public function saveRule(array $data, array $conditions, $userId = null) {
        if (!$this->isReady()) {
            return ['success' => false, 'message' => 'Tabel basis aturan belum tersedia. Jalankan migrasi database terlebih dahulu.'];
        }

        $validation = $this->validate($data, $conditions);
        if ($validation !== true) {
            return ['success' => false, 'message' => $validation];
        }

        $ruleId = !empty($data['id']) ? (int)$data['id'] : 0;

        $duplicate = $this->db->selectOne(
            "SELECT id FROM rules WHERE rule_code = ? AND id != ?",
            [$data['rule_code'], $ruleId]
        );
        if ($duplicate) {
            return ['success' => false, 'message' => 'Kode aturan ' . $data['rule_code'] . ' sudah digunakan.'];
        }

        try {
            $this->db->beginTransaction();

            if ($ruleId) {
                $this->db->update(
                    "UPDATE rules SET rule_code = ?, rule_group = ?, name = ?, description = ?, 
                            conclusion = ?, message = ?, priority = ?, is_active = ?, updated_by = ?
                     WHERE id = ?",
                    [
                        $data['rule_code'], $data['rule_group'], $data['name'], $data['description'] ?? null,
                        $data['conclusion'], $data['message'], (int)$data['priority'],
                        !empty($data['is_active']) ? 1 : 0, $userId, $ruleId
                    ]
                );
                $this->db->delete("DELETE FROM rule_conditions WHERE rule_id = ?", [$ruleId]);
            } else {
                $ruleId = $this->db->insert(
                    "INSERT INTO rules (rule_code, rule_group, name, description, conclusion, message, priority, is_active, created_by, updated_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $data['rule_code'], $data['rule_group'], $data['name'], $data['description'] ?? null,
                        $data['conclusion'], $data['message'], (int)$data['priority'],
                        !empty($data['is_active']) ? 1 : 0, $userId, $userId
                    ]
                );
            }

            $order = 1;
            foreach ($conditions as $condition) {
                $this->db->insert(
                    "INSERT INTO rule_conditions (rule_id, fact_key, operator, value_type, compare_value, fallback_value, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [
                        $ruleId,
                        $condition['fact_key'],
                        $condition['operator'],
                        $condition['value_type'] ?? 'static',
                        $condition['compare_value'],
                        ($condition['fallback_value'] ?? '') !== '' ? $condition['fallback_value'] : null,
                        $order++
                    ]
                );
            }

            $this->db->commit();

            return ['success' => true, 'message' => 'Aturan berhasil disimpan.', 'rule_id' => $ruleId];

        } catch (Exception $e) {
            $this->db->rollback();
            error_log('Save rule error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Gagal menyimpan aturan. Silakan coba lagi.'];
        }
    }

    private function validate($data, $conditions) {
        $catalog = self::factCatalog();
        $operators = self::operatorLabels();

        if (empty($data['rule_code']) || !preg_match('/^[A-Z0-9_]{2,20}$/', $data['rule_code'])) {
            return 'Kode aturan wajib diisi (huruf kapital, angka, atau garis bawah).';
        }
        if (empty($data['name'])) {
            return 'Nama aturan wajib diisi.';
        }
        if (!isset(self::groupLabels()[$data['rule_group'] ?? ''])) {
            return 'Kelompok aturan tidak valid.';
        }
        if (!isset(self::conclusionLabels()[$data['conclusion'] ?? ''])) {
            return 'Kesimpulan aturan tidak valid.';
        }
        if (empty($data['message'])) {
            return 'Pesan hasil aturan wajib diisi.';
        }
        if (!isset($data['priority']) || (int)$data['priority'] < 1) {
            return 'Prioritas harus berupa angka minimal 1.';
        }
        if (empty($conditions)) {
            return 'Aturan harus memiliki minimal satu kondisi.';
        }

        foreach ($conditions as $condition) {
            if (!isset($catalog[$condition['fact_key'] ?? ''])) {
                return 'Fakta tidak dikenali: ' . ($condition['fact_key'] ?? '-');
            }
            if (!isset($operators[$condition['operator'] ?? ''])) {
                return 'Operator tidak valid pada kondisi.';
            }
            if (!in_array($condition['value_type'] ?? 'static', ['static', 'setting', 'fact'], true)) {
                return 'Sumber nilai pembanding tidak valid.';
            }
            if (($condition['compare_value'] ?? '') === '') {
                return 'Nilai pembanding wajib diisi pada setiap kondisi.';
            }
        }

        return true;
    }

    public function setActive($ruleId, $isActive, $userId = null) {
        if (!$this->isReady()) {
            return false;
        }

        return $this->db->update(
            "UPDATE rules SET is_active = ?, updated_by = ? WHERE id = ?",
            [$isActive ? 1 : 0, $userId, (int)$ruleId]
        );
    }

    public function deleteRule($ruleId) {
        if (!$this->isReady()) {
            return ['success' => false, 'message' => 'Tabel basis aturan belum tersedia.'];
        }

        $rule = $this->db->selectOne("SELECT * FROM rules WHERE id = ?", [(int)$ruleId]);
        if (!$rule) {
            return ['success' => false, 'message' => 'Aturan tidak ditemukan.'];
        }
        if ($rule['is_system']) {
            return ['success' => false, 'message' => 'Aturan inti sistem tidak dapat dihapus. Nonaktifkan saja bila tidak dipakai.'];
        }

        $this->db->delete("DELETE FROM rules WHERE id = ?", [(int)$ruleId]);

        return ['success' => true, 'message' => 'Aturan berhasil dihapus.'];
    }
}
?>
