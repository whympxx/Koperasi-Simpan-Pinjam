<?php
/**
 * Pelaksanaan otomatis TC-15 s.d. TC-26 (Boundary Value Analysis).
 * Jalankan: php tests/run_bva_tc15_26.php
 */

define('APP_ACCESS', true);

require_once __DIR__ . '/../config/config.php';
require_once CLASSES_PATH . 'Database.php';
require_once CLASSES_PATH . 'RuleEngine.php';
require_once INCLUDES_PATH . 'functions.php';

$engine = new RuleEngine();
$results = [];
$jsonPath = __DIR__ . '/../docs/bva_tc15_26_results.json';

function baselineFacts(array $overrides = []) {
    $potongan = array_key_exists('potongan_hrd', $overrides) ? (int)$overrides['potongan_hrd'] : 1;
    $simpanan = array_key_exists('simpanan_wajib', $overrides) ? (float)$overrides['simpanan_wajib'] : 2000000;
    $kelipatan = $potongan === 1 ? 2 : 1;
    $plafon = (float)floor($simpanan * $kelipatan);

    $facts = [
        'masa_kerja_bulan'        => 24,
        'status_karyawan'         => 'active',
        'kategori_anggota'        => $potongan === 1 ? 'karyawan' : 'outsourcing',
        'potongan_hrd'            => $potongan,
        'gaji_bulanan'            => 8000000,
        'simpanan_wajib'          => $simpanan,
        'batas_kelipatan'         => $kelipatan,
        'plafon_maksimal'         => $plafon,
        'punya_pinjaman_aktif'    => 0,
        'punya_pengajuan_pending' => 0,
        'riwayat_tunggakan'       => 0,
        'pinjaman_lunas'          => 0,
        'auto_approve_aktif'      => 1,
        'jumlah_pinjaman'         => 1000000,
        'tenor_bulan'             => 12,
        'angsuran_bulanan'        => 0,
        'kelipatan_simpanan'      => 0,
        'kelipatan_gaji'          => 0,
        'rasio_angsuran_gaji'     => 10,
    ];

    $facts = array_merge($facts, $overrides);

    if (!isset($overrides['plafon_maksimal'])) {
        $kelipatan = (int)($facts['potongan_hrd'] ? 2 : 1);
        $facts['batas_kelipatan'] = $kelipatan;
        $facts['plafon_maksimal'] = (float)floor(((float)$facts['simpanan_wajib']) * $kelipatan);
    }

    return $facts;
}

function ruleStatus(array $evaluation, $code) {
    foreach ($evaluation['trace'] as $line) {
        if ($line['rule_code'] === $code) {
            return $line['status'];
        }
    }
    return 'missing';
}

function record(&$results, $id, $scenario, $expected, $actual, $passed) {
    $results[$id] = [
        'id'       => $id,
        'scenario' => $scenario,
        'expected' => $expected,
        'actual'   => $actual,
        'status'   => $passed ? 'Lulus' : 'Gagal',
        'passed'   => $passed,
    ];
}

if (!$engine->isReady()) {
    fwrite(STDERR, "Tabel rules belum tersedia. Jalankan migrasi database/add_rule_engine_tables.sql\n");
    exit(1);
}

$elig = function (array $facts) use ($engine) {
    return $engine->evaluate(RuleEngine::GROUP_ELIGIBILITY, $facts, 'eligible', 'Memenuhi seluruh syarat pengajuan.');
};
$dec = function (array $facts) use ($engine) {
    return $engine->evaluate(RuleEngine::GROUP_DECISION, $facts, 'manual_review', 'Tidak ada aturan persetujuan otomatis yang terpenuhi.');
};

// TC-15
$pass12 = $elig(baselineFacts(['masa_kerja_bulan' => 12]));
$fail11 = $elig(baselineFacts(['masa_kerja_bulan' => 11]));
$ok15 = $pass12['decision'] !== 'reject'
    && ruleStatus($pass12, 'R01') === RuleEngine::STATUS_NOT_FIRED
    && $fail11['decision'] === 'reject'
    && $fail11['rule_code'] === 'R01';
record(
    $results,
    'TC-15',
    'Nilai batas masa kerja (R01): 12 bulan dan 11 bulan',
    '12 bulan lolos penyaringan; 11 bulan ditolak dengan alasan masa kerja',
    '12 bulan: ' . $pass12['decision'] . ' (R01=' . ruleStatus($pass12, 'R01') . '); '
        . '11 bulan: ' . $fail11['decision'] . ' (' . ($fail11['rule_code'] ?: '-') . ')',
    $ok15
);

// TC-16
$pass50 = $elig(baselineFacts(['simpanan_wajib' => 50000, 'jumlah_pinjaman' => 100000]));
$fail49 = $elig(baselineFacts(['simpanan_wajib' => 49999, 'jumlah_pinjaman' => 100000]));
$ok16 = $pass50['decision'] !== 'reject'
    && ruleStatus($pass50, 'R08') === RuleEngine::STATUS_NOT_FIRED
    && $fail49['decision'] === 'reject'
    && $fail49['rule_code'] === 'R08';
record(
    $results,
    'TC-16',
    'Nilai batas simpanan wajib (R08): Rp50.000 dan Rp49.999',
    'Rp50.000 lolos; Rp49.999 ditolak, simpanan wajib di bawah minimum',
    'Rp50.000: ' . $pass50['decision'] . ' (R08=' . ruleStatus($pass50, 'R08') . '); '
        . 'Rp49.999: ' . $fail49['decision'] . ' (' . ($fail49['rule_code'] ?: '-') . ')',
    $ok16
);

// TC-17 plafon 2x
$pass4jt = $elig(baselineFacts(['potongan_hrd' => 1, 'simpanan_wajib' => 2000000, 'jumlah_pinjaman' => 4000000]));
$fail4jt = $elig(baselineFacts(['potongan_hrd' => 1, 'simpanan_wajib' => 2000000, 'jumlah_pinjaman' => 4000001]));
$ok17 = $pass4jt['decision'] !== 'reject'
    && ruleStatus($pass4jt, 'R05') === RuleEngine::STATUS_NOT_FIRED
    && $fail4jt['decision'] === 'reject'
    && $fail4jt['rule_code'] === 'R05';
record(
    $results,
    'TC-17',
    'Nilai batas plafon 2 × simpanan wajib (R05): Rp4.000.000 dan Rp4.000.001 atas simpanan Rp2.000.000',
    'Nilai tepat plafon diterima; kelebihan Rp1 ditolak',
    'Rp4.000.000: ' . $pass4jt['decision'] . ' (R05=' . ruleStatus($pass4jt, 'R05') . '); '
        . 'Rp4.000.001: ' . $fail4jt['decision'] . ' (' . ($fail4jt['rule_code'] ?: '-') . ')',
    $ok17
);

// TC-18 plafon 1x
$pass2jt = $elig(baselineFacts(['potongan_hrd' => 0, 'simpanan_wajib' => 2000000, 'jumlah_pinjaman' => 2000000]));
$fail2jt = $elig(baselineFacts(['potongan_hrd' => 0, 'simpanan_wajib' => 2000000, 'jumlah_pinjaman' => 2000001]));
$ok18 = $pass2jt['decision'] !== 'reject'
    && ruleStatus($pass2jt, 'R05') === RuleEngine::STATUS_NOT_FIRED
    && $fail2jt['decision'] === 'reject'
    && $fail2jt['rule_code'] === 'R05';
record(
    $results,
    'TC-18',
    'Nilai batas plafon 1 × simpanan wajib (R05): Rp2.000.000 dan Rp2.000.001 atas simpanan Rp2.000.000',
    'Nilai tepat plafon diterima; kelebihan Rp1 ditolak',
    'Rp2.000.000: ' . $pass2jt['decision'] . ' (R05=' . ruleStatus($pass2jt, 'R05') . '); '
        . 'Rp2.000.001: ' . $fail2jt['decision'] . ' (' . ($fail2jt['rule_code'] ?: '-') . ')',
    $ok18
);

// TC-19 tenor 18/19
$pass18 = $elig(baselineFacts(['tenor_bulan' => 18]));
$fail19 = $elig(baselineFacts(['tenor_bulan' => 19]));
$ok19 = $pass18['decision'] !== 'reject'
    && ruleStatus($pass18, 'R06') === RuleEngine::STATUS_NOT_FIRED
    && $fail19['decision'] === 'reject'
    && $fail19['rule_code'] === 'R06';
record(
    $results,
    'TC-19',
    'Nilai batas tenor maksimum kebijakan (R06): 18 bulan dan 19 bulan',
    '18 bulan lolos penyaringan; 19 bulan ditolak sistem',
    '18 bulan: ' . $pass18['decision'] . ' (R06=' . ruleStatus($pass18, 'R06') . '); '
        . '19 bulan: ' . $fail19['decision'] . ' (' . ($fail19['rule_code'] ?: '-') . ')',
    $ok19
);

// TC-20 R07 — uji saat kaidah diaktifkan, lalu kembalikan status semula
$db = Database::getInstance();
$r07 = $db->selectOne("SELECT id, is_active FROM rules WHERE rule_code = 'R07'");
$r07WasActive = $r07 ? (int)$r07['is_active'] === 1 : false;
if ($r07) {
    $engine->setActive((int)$r07['id'], true);
}
$r07off40 = null;
$r07off41 = null;
if ($r07 && !$r07WasActive) {
    $engine->setActive((int)$r07['id'], false);
    $r07off40 = $elig(baselineFacts(['rasio_angsuran_gaji' => 40, 'simpanan_wajib' => 10000000, 'jumlah_pinjaman' => 1000000]));
    $r07off41 = $elig(baselineFacts(['rasio_angsuran_gaji' => 41, 'simpanan_wajib' => 10000000, 'jumlah_pinjaman' => 1000000]));
    $engine->setActive((int)$r07['id'], true);
}
$ratio40 = $elig(baselineFacts(['rasio_angsuran_gaji' => 40, 'simpanan_wajib' => 10000000, 'jumlah_pinjaman' => 1000000]));
$ratio41 = $elig(baselineFacts(['rasio_angsuran_gaji' => 41, 'simpanan_wajib' => 10000000, 'jumlah_pinjaman' => 1000000]));
if ($r07) {
    $engine->setActive((int)$r07['id'], $r07WasActive);
}
$ok20 = ruleStatus($ratio40, 'R07') === RuleEngine::STATUS_NOT_FIRED
    && $ratio40['decision'] !== 'reject'
    && $ratio41['decision'] === 'reject'
    && $ratio41['rule_code'] === 'R07'
    && ($r07off41 === null || $r07off41['decision'] !== 'reject' || $r07off41['rule_code'] !== 'R07');
$actual20 = 'R07 aktif: 40% ' . $ratio40['decision'] . ' (R07=' . ruleStatus($ratio40, 'R07') . '); '
    . '41% ' . $ratio41['decision'] . ' (' . ($ratio41['rule_code'] ?: '-') . ')';
if ($r07off41) {
    $actual20 .= '; R07 nonaktif: 41% ' . $r07off41['decision'] . ' (' . ($r07off41['rule_code'] ?: '-') . ')';
}
record(
    $results,
    'TC-20',
    'Nilai batas rasio angsuran terhadap gaji (R07, nonaktif): 40% dan 41%',
    'Tidak terpicu pada 40%; terpicu pada 41% bila kaidah diaktifkan',
    $actual20,
    $ok20
);

// TC-21 auto-approve amount — plafon harus >= 5.000.001
$auto5m = $dec(baselineFacts([
    'simpanan_wajib' => 3000000,
    'potongan_hrd' => 1,
    'jumlah_pinjaman' => 5000000,
    'tenor_bulan' => 12,
    'auto_approve_aktif' => 1,
    'riwayat_tunggakan' => 0,
]));
$review5m = $dec(baselineFacts([
    'simpanan_wajib' => 3000000,
    'potongan_hrd' => 1,
    'jumlah_pinjaman' => 5000001,
    'tenor_bulan' => 12,
    'auto_approve_aktif' => 1,
    'riwayat_tunggakan' => 0,
]));
$ok21 = $auto5m['decision'] === 'approve'
    && $review5m['decision'] === 'manual_review'
    && $review5m['rule_code'] === 'R11';
record(
    $results,
    'TC-21',
    'Nilai batas nominal persetujuan otomatis (R11): Rp5.000.000 dan Rp5.000.001',
    'Rp5.000.000 disetujui otomatis; Rp5.000.001 peninjauan manual',
    'Rp5.000.000: ' . $auto5m['decision'] . ' (' . ($auto5m['rule_code'] ?: '-') . '); '
        . 'Rp5.000.001: ' . $review5m['decision'] . ' (' . ($review5m['rule_code'] ?: '-') . ')',
    $ok21
);

// TC-22 tenor auto-approve
$auto12 = $dec(baselineFacts(['jumlah_pinjaman' => 1000000, 'tenor_bulan' => 12, 'auto_approve_aktif' => 1, 'potongan_hrd' => 1]));
$review13 = $dec(baselineFacts(['jumlah_pinjaman' => 1000000, 'tenor_bulan' => 13, 'auto_approve_aktif' => 1, 'potongan_hrd' => 1]));
$ok22 = $auto12['decision'] === 'approve'
    && $review13['decision'] === 'manual_review'
    && in_array($review13['rule_code'], ['R12', 'R15'], true);
record(
    $results,
    'TC-22',
    'Nilai batas tenor persetujuan otomatis (R12/R15): 12 bulan dan 13 bulan',
    '12 bulan disetujui otomatis; 13 bulan peninjauan manual',
    '12 bulan: ' . $auto12['decision'] . ' (' . ($auto12['rule_code'] ?: '-') . '); '
        . '13 bulan: ' . $review13['decision'] . ' (' . ($review13['rule_code'] ?: '-') . ')',
    $ok22
);

// TC-23 tunggakan
$late2 = $dec(baselineFacts(['riwayat_tunggakan' => 2, 'jumlah_pinjaman' => 1000000, 'tenor_bulan' => 12, 'auto_approve_aktif' => 1, 'potongan_hrd' => 1]));
$late3 = $dec(baselineFacts(['riwayat_tunggakan' => 3, 'jumlah_pinjaman' => 1000000, 'tenor_bulan' => 12, 'auto_approve_aktif' => 1, 'potongan_hrd' => 1]));
$ok23 = ruleStatus($late2, 'R13') !== RuleEngine::STATUS_FIRED
    && $late2['decision'] === 'approve'
    && $late3['decision'] === 'manual_review'
    && $late3['rule_code'] === 'R13';
record(
    $results,
    'TC-23',
    'Nilai batas riwayat tunggakan (R13): 2 kali dan 3 kali',
    'Dua kali tidak memicu kaidah; tiga kali memicu peninjauan manual',
    '2 kali: ' . $late2['decision'] . ' (R13=' . ruleStatus($late2, 'R13') . '); '
        . '3 kali: ' . $late3['decision'] . ' (' . ($late3['rule_code'] ?: '-') . ')',
    $ok23
);

// TC-24 form min amount
$ok100k = validateLoanFormInput(100000, 12, 4000000, 18);
$bad99999 = validateLoanFormInput(99999, 12, 4000000, 18);
$ok24 = !empty($ok100k['valid']) && empty($bad99999['valid']) && ($bad99999['field'] ?? '') === 'loan_amount';
record(
    $results,
    'TC-24',
    'Nilai batas nominal minimum formulir: Rp100.000 dan Rp99.999',
    'Rp100.000 diterima; Rp99.999 ditolak validasi formulir',
    'Rp100.000: ' . ($ok100k['valid'] ? 'diterima' : $ok100k['message']) . '; '
        . 'Rp99.999: ' . ($bad99999['valid'] ? 'diterima' : $bad99999['message']),
    $ok24
);

// TC-25 form tenor domain
$t1 = validateLoanFormInput(100000, 1, 4000000, 18);
$t18 = validateLoanFormInput(100000, 18, 4000000, 18);
$t0 = validateLoanFormInput(100000, 0, 4000000, 18);
$t19 = validateLoanFormInput(100000, 19, 4000000, 18);
$ok25 = !empty($t1['valid']) && !empty($t18['valid']) && empty($t0['valid']) && empty($t19['valid']);
record(
    $results,
    'TC-25',
    'Nilai batas domain tenor formulir: 1 dan 18 bulan serta 0 dan 19 bulan',
    'Nilai 1 dan 18 diterima; nilai 0 dan 19 ditolak validasi tenor',
    '1: ' . ($t1['valid'] ? 'diterima' : $t1['message'])
        . '; 18: ' . ($t18['valid'] ? 'diterima' : $t18['message'])
        . '; 0: ' . ($t0['valid'] ? 'diterima' : $t0['message'])
        . '; 19: ' . ($t19['valid'] ? 'diterima' : $t19['message']),
    $ok25
);

// TC-26 discrete switches
$on1 = $dec(baselineFacts(['auto_approve_aktif' => 1, 'potongan_hrd' => 1, 'jumlah_pinjaman' => 1000000, 'tenor_bulan' => 12]));
$offAuto = $dec(baselineFacts(['auto_approve_aktif' => 0, 'potongan_hrd' => 1, 'jumlah_pinjaman' => 1000000, 'tenor_bulan' => 12]));
$offHrd = $dec(baselineFacts(['auto_approve_aktif' => 1, 'potongan_hrd' => 0, 'jumlah_pinjaman' => 1000000, 'tenor_bulan' => 12]));
$ok26 = $on1['decision'] === 'approve'
    && ruleStatus($on1, 'R10') !== RuleEngine::STATUS_FIRED
    && ruleStatus($on1, 'R16') !== RuleEngine::STATUS_FIRED
    && $offAuto['decision'] === 'manual_review' && $offAuto['rule_code'] === 'R10'
    && $offHrd['decision'] === 'manual_review' && $offHrd['rule_code'] === 'R16';
record(
    $results,
    'TC-26',
    'Nilai diskrit sakelar persetujuan otomatis (R10) dan potongan gaji HRD (R16): 1 dan 0',
    'Nilai 1 tidak memicu kaidah; nilai 0 memicu peninjauan manual',
    'Nilai 1: ' . $on1['decision'] . ' (R10=' . ruleStatus($on1, 'R10') . ', R16=' . ruleStatus($on1, 'R16') . '); '
        . 'auto-approve 0: ' . $offAuto['decision'] . ' (' . ($offAuto['rule_code'] ?: '-') . '); '
        . 'potongan HRD 0: ' . $offHrd['decision'] . ' (' . ($offHrd['rule_code'] ?: '-') . ')',
    $ok26
);

$payload = [
    'executed_at' => date('Y-m-d H:i:s'),
    'passed'      => count(array_filter($results, function ($row) { return $row['passed']; })),
    'total'       => count($results),
    'cases'       => array_values($results),
];

if (!is_dir(dirname($jsonPath))) {
    mkdir(dirname($jsonPath), 0755, true);
}
file_put_contents($jsonPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$failed = $payload['total'] - $payload['passed'];
echo "BVA TC-15 s.d. TC-26: {$payload['passed']}/{$payload['total']} lulus" . PHP_EOL;
foreach ($results as $row) {
    echo sprintf("[%s] %s — %s\n  Aktual: %s\n", $row['status'], $row['id'], $row['scenario'], $row['actual']);
}

exit($failed > 0 ? 1 : 0);
