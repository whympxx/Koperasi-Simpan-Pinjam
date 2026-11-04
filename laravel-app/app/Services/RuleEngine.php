<?php

namespace App\Services;

use App\Models\Rule;
use App\Models\RuleEvaluationLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mesin aturan IF-THEN (forward chaining sederhana).
 * Aturan aktif dievaluasi berurutan berdasarkan prioritas; aturan pertama
 * yang seluruh kondisinya terpenuhi (AND) menentukan kesimpulan.
 */
class RuleEngine
{
    public const GROUP_ELIGIBILITY = 'loan_eligibility';
    public const GROUP_DECISION = 'loan_decision';

    public const FIRED = 'fired';
    public const NOT_FIRED = 'not_fired';
    public const SKIPPED = 'skipped';

    public static function factCatalog(): array
    {
        return [
            'masa_kerja_bulan' => 'Masa kerja (bulan)',
            'status_karyawan' => 'Status kepegawaian',
            'kategori_anggota' => 'Kategori anggota (karyawan/driver/outsourcing)',
            'potongan_hrd' => 'Angsuran via potongan HRD (1/0)',
            'gaji_bulanan' => 'Gaji/komisi bulanan (Rp)',
            'simpanan_wajib' => 'Saldo simpanan wajib (Rp)',
            'batas_kelipatan' => 'Batas kelipatan plafon (1 atau 2)',
            'plafon_maksimal' => 'Plafon pinjaman maksimal (Rp)',
            'kelipatan_simpanan' => 'Kelipatan simpanan wajib (pinjaman / simpanan)',
            'punya_pinjaman_aktif' => 'Punya pinjaman aktif (1/0)',
            'punya_pengajuan_pending' => 'Punya pengajuan diproses (1/0)',
            'riwayat_tunggakan' => 'Jumlah angsuran pernah terlambat',
            'pinjaman_lunas' => 'Jumlah pinjaman yang sudah lunas',
            'jumlah_pinjaman' => 'Jumlah pinjaman diajukan (Rp)',
            'tenor_bulan' => 'Tenor pinjaman (bulan)',
            'angsuran_bulanan' => 'Angsuran bulanan (Rp)',
            'kelipatan_gaji' => 'Kelipatan gaji (pinjaman / gaji)',
            'rasio_angsuran_gaji' => 'Rasio angsuran terhadap gaji (%)',
            'auto_approve_aktif' => 'Fitur auto-approve aktif (1/0)',
        ];
    }

    public static function operators(): array
    {
        return [
            '<' => 'kurang dari',
            '<=' => 'kurang dari atau sama dengan',
            '=' => 'sama dengan',
            '!=' => 'tidak sama dengan',
            '>=' => 'lebih dari atau sama dengan',
            '>' => 'lebih dari',
            'in' => 'termasuk salah satu dari',
            'not_in' => 'tidak termasuk dari',
        ];
    }

    public static function groups(): array
    {
        return [
            self::GROUP_ELIGIBILITY => 'Kelayakan Pengajuan Pinjaman',
            self::GROUP_DECISION => 'Keputusan Persetujuan Pinjaman',
        ];
    }

    public static function conclusions(): array
    {
        return [
            'approve' => 'Setujui otomatis',
            'reject' => 'Tolak pengajuan',
            'manual_review' => 'Review manual admin',
        ];
    }

    public static function factLabel(string $key): string
    {
        return self::factCatalog()[$key] ?? $key;
    }

    public function hasActiveRules(string $group): bool
    {
        return Rule::where('rule_group', $group)->where('is_active', true)->exists();
    }

    public function activeRules(string $group): Collection
    {
        return Rule::with('conditions')
            ->where('rule_group', $group)
            ->where('is_active', true)
            ->orderBy('priority')->orderBy('id')
            ->get();
    }

    public function evaluate(string $group, array $facts, string $defaultDecision, string $defaultMessage = ''): array
    {
        $facts = array_map(fn ($v) => is_bool($v) ? (int) $v : $v, $facts);

        $result = [
            'group' => $group,
            'decision' => $defaultDecision,
            'rule_code' => null,
            'rule_name' => null,
            'message' => $defaultMessage,
            'facts' => $facts,
            'fired' => [],
            'trace' => [],
        ];

        foreach ($this->activeRules($group) as $rule) {
            $evaluation = $this->evaluateRule($rule, $facts);
            $result['trace'][] = $evaluation;

            if ($evaluation['status'] !== self::FIRED) {
                continue;
            }

            $result['fired'][] = $rule->rule_code;

            if ($result['rule_code'] === null) {
                $result['decision'] = $rule->conclusion;
                $result['rule_code'] = $rule->rule_code;
                $result['rule_name'] = $rule->name;
                $result['message'] = $evaluation['message'];
            }
        }

        return $result;
    }

    private function evaluateRule(Rule $rule, array $facts): array
    {
        $lines = $rule->conditions->map(fn ($c) => $this->evaluateCondition($c->toArray(), $facts))->all();

        $statuses = array_column($lines, 'status');
        $status = match (true) {
            $lines === [] => self::SKIPPED,
            in_array(self::NOT_FIRED, $statuses, true) => self::NOT_FIRED,
            in_array(self::SKIPPED, $statuses, true) => self::SKIPPED,
            default => self::FIRED,
        };

        $first = $lines[0] ?? ['expected' => '', 'actual' => ''];

        return [
            'rule_code' => $rule->rule_code,
            'rule_name' => $rule->name,
            'conclusion' => $rule->conclusion,
            'priority' => $rule->priority,
            'status' => $status,
            'if_text' => $this->describe($rule),
            'conditions' => $lines,
            'message' => strtr($rule->message, [
                '{nilai}' => $this->display($first['expected']),
                '{fakta}' => $this->display($first['actual']),
            ]),
        ];
    }

    private function evaluateCondition(array $condition, array $facts): array
    {
        $key = $condition['fact_key'];
        $expected = $this->resolveValue($condition, $facts);

        $line = [
            'fact_key' => $key,
            'fact_label' => self::factLabel($key),
            'operator' => $condition['operator'],
            'expected' => $expected,
            'actual' => null,
            'status' => self::SKIPPED,
            'note' => '',
        ];

        if (! array_key_exists($key, $facts) || $facts[$key] === null) {
            $line['note'] = 'Fakta tidak tersedia pada konteks ini';

            return $line;
        }
        if ($expected === null) {
            $line['note'] = 'Nilai pembanding tidak ditemukan';

            return $line;
        }

        $line['actual'] = $facts[$key];
        $line['status'] = $this->compare($facts[$key], $condition['operator'], $expected) ? self::FIRED : self::NOT_FIRED;

        return $line;
    }

    private function resolveValue(array $condition, array $facts)
    {
        $value = $condition['compare_value'];
        $fallback = $condition['fallback_value'] ?? null;

        return match ($condition['value_type'] ?? 'static') {
            'setting' => (($s = Settings::get($value, '')) === '' || $s === null) ? $fallback : (is_bool($s) ? (int) $s : $s),
            'fact' => (array_key_exists($value, $facts) && $facts[$value] !== null) ? $facts[$value] : $fallback,
            default => $value,
        };
    }

    public function compare($actual, string $operator, $expected): bool
    {
        if ($operator === 'in' || $operator === 'not_in') {
            $found = false;
            foreach (array_map('trim', explode(',', (string) $expected)) as $item) {
                if ($this->compare($actual, '=', $item)) {
                    $found = true;
                    break;
                }
            }

            return $operator === 'in' ? $found : ! $found;
        }

        $numeric = is_numeric($actual) && is_numeric($expected);
        $left = $numeric ? (float) $actual : mb_strtolower(trim((string) $actual));
        $right = $numeric ? (float) $expected : mb_strtolower(trim((string) $expected));

        return match ($operator) {
            '<' => $left < $right,
            '<=' => $left <= $right,
            '>' => $left > $right,
            '>=' => $left >= $right,
            '=' => $numeric ? abs($left - $right) < 0.000001 : $left === $right,
            '!=' => $numeric ? abs($left - $right) >= 0.000001 : $left !== $right,
            default => false,
        };
    }

    public function describe(Rule $rule): string
    {
        $parts = $rule->conditions->map(function ($c) {
            $value = match ($c->value_type) {
                'setting' => 'pengaturan['.$c->compare_value.']',
                'fact' => 'fakta['.$c->compare_value.']',
                default => $c->compare_value,
            };

            return self::factLabel($c->fact_key).' '.$c->operator.' '.$value;
        })->all();

        return $parts ? implode(' DAN ', $parts) : '(tanpa kondisi)';
    }

    private function display($value): string
    {
        if (is_numeric($value)) {
            $n = (float) $value;

            return number_format($n, floor($n) == $n ? 0 : 2, ',', '.');
        }

        return (string) $value;
    }

    public function log(array $result, ?int $userId, ?string $entityType = null, ?int $entityId = null): void
    {
        RuleEvaluationLog::create([
            'user_id' => $userId,
            'rule_group' => $result['group'],
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'decision' => $result['decision'],
            'fired_rule_code' => $result['rule_code'],
            'facts' => $result['facts'],
            'trace' => $result['trace'],
        ]);
    }

    public function nextRuleCode(): string
    {
        $max = Rule::pluck('rule_code')
            ->filter(fn ($code) => preg_match('/^R\d+$/', $code))
            ->map(fn ($code) => (int) substr($code, 1))
            ->max() ?? 0;

        return 'R'.str_pad((string) ($max + 1), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Simpan aturan beserta kondisinya.
     *
     * @throws InvalidArgumentException bila data tidak valid
     */
    public function save(array $data, array $conditions, ?int $userId, ?Rule $rule = null): Rule
    {
        $this->validate($data, $conditions);

        $duplicate = Rule::where('rule_code', $data['rule_code'])
            ->when($rule, fn ($q) => $q->where('id', '!=', $rule->id))
            ->exists();
        if ($duplicate) {
            throw new InvalidArgumentException('Kode aturan '.$data['rule_code'].' sudah digunakan.');
        }

        return DB::transaction(function () use ($data, $conditions, $userId, $rule) {
            $attributes = [
                'rule_code' => $data['rule_code'],
                'rule_group' => $data['rule_group'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'conclusion' => $data['conclusion'],
                'message' => $data['message'],
                'priority' => (int) $data['priority'],
                'is_active' => ! empty($data['is_active']),
                'updated_by' => $userId,
            ];

            if ($rule) {
                $rule->update($attributes);
                $rule->conditions()->delete();
            } else {
                $rule = Rule::create($attributes + ['created_by' => $userId]);
            }

            foreach (array_values($conditions) as $i => $condition) {
                $rule->conditions()->create([
                    'fact_key' => $condition['fact_key'],
                    'operator' => $condition['operator'],
                    'value_type' => $condition['value_type'] ?? 'static',
                    'compare_value' => $condition['compare_value'],
                    'fallback_value' => ($condition['fallback_value'] ?? '') !== '' ? $condition['fallback_value'] : null,
                    'sort_order' => $i + 1,
                ]);
            }

            return $rule->load('conditions');
        });
    }

    private function validate(array $data, array $conditions): void
    {
        $fail = fn (string $msg) => throw new InvalidArgumentException($msg);

        if (! preg_match('/^[A-Z0-9_]{2,20}$/', $data['rule_code'] ?? '')) {
            $fail('Kode aturan wajib diisi (huruf kapital, angka, atau garis bawah, 2-20 karakter).');
        }
        if (trim($data['name'] ?? '') === '') {
            $fail('Nama aturan wajib diisi.');
        }
        if (! isset(self::groups()[$data['rule_group'] ?? ''])) {
            $fail('Kelompok aturan tidak valid.');
        }
        if (! isset(self::conclusions()[$data['conclusion'] ?? ''])) {
            $fail('Kesimpulan aturan tidak valid.');
        }
        if (trim($data['message'] ?? '') === '') {
            $fail('Pesan hasil aturan wajib diisi.');
        }
        if ((int) ($data['priority'] ?? 0) < 1) {
            $fail('Prioritas harus berupa angka minimal 1.');
        }
        if ($conditions === []) {
            $fail('Aturan harus memiliki minimal satu kondisi.');
        }

        foreach ($conditions as $c) {
            if (! isset(self::factCatalog()[$c['fact_key'] ?? ''])) {
                $fail('Fakta tidak dikenali: '.($c['fact_key'] ?? '-'));
            }
            if (! isset(self::operators()[$c['operator'] ?? ''])) {
                $fail('Operator tidak valid pada kondisi.');
            }
            if (! in_array($c['value_type'] ?? 'static', ['static', 'setting', 'fact'], true)) {
                $fail('Sumber nilai pembanding tidak valid.');
            }
            if (($c['compare_value'] ?? '') === '') {
                $fail('Nilai pembanding wajib diisi pada setiap kondisi.');
            }
            if (($c['value_type'] ?? 'static') === 'fact' && ! isset(self::factCatalog()[$c['compare_value']])) {
                $fail('Fakta pembanding tidak dikenali: '.$c['compare_value']);
            }
        }
    }
}
