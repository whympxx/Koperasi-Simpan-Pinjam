<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Division;
use App\Models\Rule;
use App\Models\Setting;
use App\Models\User;
use App\Services\SavingsService;
use App\Services\Settings;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSettings();
        $this->seedRules();

        $divisions = collect([
            ['Human Resources', 'HRD'], ['Finance & Accounting', 'FIN'], ['Operations', 'OPS'],
            ['Logistics', 'LOG'], ['Information Technology', 'IT'], ['Marketing', 'MKT'],
        ])->mapWithKeys(fn ($d) => [$d[1] => Division::firstOrCreate(['code' => $d[1]], ['name' => $d[0]])->id]);

        // Semua akun demo memakai password: Password123
        $admins = [
            ['ADMIN001', 'Administrator System', 'admin@puninar.com', 'super_admin', 'HRD', 'System Administrator'],
            ['HRD001', 'Siti Nurhaliza', 'hrd@puninar.com', 'admin', 'HRD', 'HRD Manager'],
            ['FIN001', 'Budi Santoso', 'finance@puninar.com', 'admin', 'FIN', 'Finance Manager'],
        ];
        foreach ($admins as $i => [$nik, $name, $email, $role, $div, $pos]) {
            User::firstOrCreate(['nik' => $nik], [
                'full_name' => $name, 'email' => $email, 'phone' => '08123456789'.$i, 'password' => 'Password123',
                'role' => $role, 'division_id' => $divisions[$div], 'position' => $pos,
                'employment_date' => '2020-01-01', 'monthly_salary' => 12000000,
            ]);
        }

        $admin = User::where('nik', 'ADMIN001')->first();
        $savings = app(SavingsService::class);

        $members = [
            // nik, nama, email, divisi, jabatan, kategori, potongan HRD, mulai kerja, gaji, simpanan wajib
            ['PUN001', 'Ahmad Fauzi', 'ahmad.fauzi@puninar.com', 'LOG', 'Driver', 'driver', true, '2019-03-01', 5000000, 3000000],
            ['PUN002', 'Dewi Sartika', 'dewi.sartika@puninar.com', 'OPS', 'Supervisor Operasional', 'karyawan', true, '2018-06-15', 8000000, 4000000],
            ['PUN003', 'Rizki Pratama', 'rizki.pratama@puninar.com', 'IT', 'IT Support', 'outsourcing', false, '2021-01-10', 6500000, 1500000],
            ['PUN004', 'Lina Marlina', 'lina.marlina@puninar.com', 'MKT', 'Staff Marketing', 'karyawan', true, now()->subMonths(6)->toDateString(), 5500000, 300000],
        ];
        foreach ($members as $i => [$nik, $name, $email, $div, $pos, $cat, $payroll, $since, $salary, $balance]) {
            $user = User::firstOrCreate(['nik' => $nik], [
                'full_name' => $name, 'email' => $email, 'phone' => '08134567890'.$i, 'password' => 'Password123',
                'role' => 'nasabah', 'division_id' => $divisions[$div], 'position' => $pos,
                'member_category' => $cat, 'payroll_deduction' => $payroll, 'employment_date' => $since,
                'monthly_salary' => $salary,
            ]);
            if (! $user->savingsAccount()->exists()) {
                $savings->createAccount($user);
                $savings->deposit($user, $balance, 'payroll_deduction', 'Saldo awal simpanan wajib', $admin);
            }
        }

        if (! Announcement::exists()) {
            Announcement::create([
                'title' => 'Selamat datang di layanan digital KOPPUJA',
                'body' => 'Kini setoran simpanan wajib, pengajuan pinjaman, pembayaran angsuran, dan laporan SHU dapat dipantau secara online. Hubungi pengurus bila ada kendala.',
                'category' => 'info',
                'created_by' => $admin->id,
            ]);
        }
    }

    private function seedSettings(): void
    {
        $descriptions = [
            'loan_interest_rate' => 'Suku bunga pinjaman per bulan, flat (%)',
            'max_loan_multiplier' => 'Plafon standar (kelipatan simpanan wajib) - potongan HRD/Yayasan',
            'max_loan_multiplier_limited' => 'Plafon terbatas (kelipatan simpanan wajib) - outsourcing / angsur mandiri',
            'max_loan_term' => 'Tenor maksimal kebijakan khusus (bulan)',
            'max_loan_term_standard' => 'Tenor standar tanpa surat pernyataan (bulan)',
            'min_mandatory_savings' => 'Minimal simpanan wajib (Rp)',
            'monthly_mandatory_savings' => 'Iuran simpanan wajib per bulan via potong gaji (Rp)',
            'min_employment_months' => 'Minimal masa kerja untuk pinjaman (bulan)',
            'min_loan_amount' => 'Nominal minimum pengajuan pinjaman (Rp)',
            'late_fee_percentage' => 'Denda keterlambatan per hari dari nilai angsuran (%)',
            'loan_auto_approve_enabled' => 'Aktifkan persetujuan pinjaman otomatis',
            'loan_auto_approve_max_amount' => 'Batas jumlah pinjaman untuk auto-approve (Rp)',
            'loan_auto_approve_max_term_months' => 'Batas tenor untuk auto-approve (bulan)',
        ];

        foreach (config('koperasi.defaults') as $key => $value) {
            if (! Setting::where('setting_key', $key)->exists()) {
                Settings::set($key, $value, is_bool($value) ? 'boolean' : 'number', $descriptions[$key] ?? 'Alokasi SHU (%)');
            }
        }
    }

    private function seedRules(): void
    {
        // [kode, kelompok, nama, kesimpulan, pesan, prioritas, aktif, sistem, [[fakta, op, tipe, nilai, cadangan]]]
        $rules = [
            ['R01', 'loan_eligibility', 'Masa kerja belum memenuhi syarat', 'reject', 'Masa kerja minimal {nilai} bulan. Masa kerja Anda: {fakta} bulan.', 10, true, true, [['masa_kerja_bulan', '<', 'setting', 'min_employment_months', '12']]],
            ['R02', 'loan_eligibility', 'Masih memiliki pinjaman aktif', 'reject', 'Anda sudah memiliki pinjaman aktif. Selesaikan pinjaman yang ada terlebih dahulu.', 20, true, true, [['punya_pinjaman_aktif', '=', 'static', '1', null]]],
            ['R03', 'loan_eligibility', 'Masih ada pengajuan yang diproses', 'reject', 'Anda memiliki pengajuan pinjaman yang sedang diproses.', 30, true, true, [['punya_pengajuan_pending', '=', 'static', '1', null]]],
            ['R04', 'loan_eligibility', 'Status kepegawaian tidak aktif', 'reject', 'Status kepegawaian Anda tidak aktif sehingga tidak dapat mengajukan pinjaman.', 40, true, false, [['status_karyawan', '!=', 'static', 'active', null]]],
            ['R08', 'loan_eligibility', 'Simpanan wajib di bawah minimal', 'reject', 'Simpanan wajib minimal Rp {nilai}. Saldo simpanan wajib Anda: Rp {fakta}.', 45, true, true, [['simpanan_wajib', '<', 'setting', 'min_mandatory_savings', '50000']]],
            ['R05', 'loan_eligibility', 'Jumlah pinjaman melebihi plafon simpanan wajib', 'reject', 'Jumlah pinjaman melebihi plafon maksimal Rp {nilai} (kelipatan simpanan wajib sesuai status potongan gaji Anda).', 50, true, true, [['jumlah_pinjaman', '>', 'fact', 'plafon_maksimal', '0']]],
            ['R06', 'loan_eligibility', 'Tenor melebihi batas maksimal kebijakan', 'reject', 'Jangka waktu pinjaman maksimal {nilai} bulan (kebijakan khusus dengan surat pernyataan).', 60, true, true, [['tenor_bulan', '>', 'setting', 'max_loan_term', '18']]],
            ['R07', 'loan_eligibility', 'Angsuran melebihi kemampuan bayar', 'reject', 'Angsuran bulanan ({fakta}% dari gaji) melebihi batas maksimal {nilai}% dari gaji.', 70, false, false, [['rasio_angsuran_gaji', '>', 'static', '40', null]]],
            ['R10', 'loan_decision', 'Persetujuan otomatis dinonaktifkan', 'manual_review', 'Persetujuan otomatis sedang dinonaktifkan, pengajuan diteruskan ke review admin.', 10, true, true, [['auto_approve_aktif', '=', 'static', '0', null]]],
            ['R11', 'loan_decision', 'Jumlah pinjaman di atas batas auto-approve', 'manual_review', 'Jumlah pinjaman melebihi batas persetujuan otomatis (Rp {nilai}).', 20, true, true, [['jumlah_pinjaman', '>', 'setting', 'loan_auto_approve_max_amount', '5000000']]],
            ['R16', 'loan_decision', 'Anggota tanpa potongan HRD / outsourcing', 'manual_review', 'Anggota outsourcing / angsuran mandiri memerlukan verifikasi kemampuan bayar oleh pengurus.', 22, true, true, [['potongan_hrd', '=', 'static', '0', null]]],
            ['R15', 'loan_decision', 'Tenor di atas standar (butuh surat pernyataan)', 'manual_review', 'Tenor di atas {nilai} bulan memerlukan surat pernyataan bermaterai dan review pengurus.', 25, true, true, [['tenor_bulan', '>', 'setting', 'max_loan_term_standard', '12']]],
            ['R12', 'loan_decision', 'Tenor di atas batas auto-approve', 'manual_review', 'Tenor pinjaman melebihi batas persetujuan otomatis ({nilai} bulan).', 30, true, true, [['tenor_bulan', '>', 'setting', 'loan_auto_approve_max_term_months', '12']]],
            ['R13', 'loan_decision', 'Memiliki riwayat tunggakan', 'manual_review', 'Nasabah memiliki riwayat keterlambatan angsuran sebanyak {fakta} kali.', 40, true, false, [['riwayat_tunggakan', '>=', 'static', '3', null]]],
            ['R14', 'loan_decision', 'Memenuhi seluruh kriteria auto-approve', 'approve', 'Disetujui otomatis oleh sistem (memenuhi kriteria auto-approve).', 999, true, true, [['jumlah_pinjaman', '>=', 'static', '0', null]]],
        ];

        foreach ($rules as [$code, $group, $name, $conclusion, $message, $priority, $active, $system, $conditions]) {
            if (Rule::where('rule_code', $code)->exists()) {
                continue;
            }
            $rule = Rule::create(compact('name', 'conclusion', 'message', 'priority') + [
                'rule_code' => $code, 'rule_group' => $group, 'is_active' => $active, 'is_system' => $system,
            ]);
            foreach ($conditions as $i => [$fact, $op, $type, $value, $fallback]) {
                $rule->conditions()->create([
                    'fact_key' => $fact, 'operator' => $op, 'value_type' => $type,
                    'compare_value' => $value, 'fallback_value' => $fallback, 'sort_order' => $i + 1,
                ]);
            }
        }
    }
}
