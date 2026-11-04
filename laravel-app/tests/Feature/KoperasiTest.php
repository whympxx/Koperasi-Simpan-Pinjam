<?php

namespace Tests\Feature;

use App\Models\ActiveLoan;
use App\Models\ActivityLog;
use App\Models\LoanApplication;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\LoanService;
use App\Services\ShuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class KoperasiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function user(string $nik): User
    {
        return User::where('nik', $nik)->firstOrFail();
    }

    private function apply(string $nik, int $amount, int $term)
    {
        return $this->actingAs($this->user($nik))->post(route('loans.store'), [
            'loan_amount' => number_format($amount, 0, ',', '.'),
            'term_months' => $term,
            'purpose' => 'Pendidikan',
            'agreement' => '1',
        ]);
    }

    // ---- Autentikasi ----

    public function test_wrong_password_shows_correct_message_and_is_logged(): void
    {
        $this->post('/login', ['nik' => 'PUN001', 'password' => 'salah'])
            ->assertSessionHasErrors(['nik' => 'NIK atau password salah.']);

        $this->assertDatabaseHas('activity_logs', ['action' => 'failed_login', 'user_id' => null]);
        $this->assertGuest();
    }

    public function test_login_with_remember_me_redirects_by_role(): void
    {
        $this->post('/login', ['nik' => 'pun001', 'password' => 'Password123', 'remember' => '1'])
            ->assertRedirect(route('dashboard'));
        $this->assertNotNull($this->user('PUN001')->fresh()->remember_token);

        auth()->logout();
        $this->post('/login', ['nik' => 'ADMIN001', 'password' => 'Password123'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_inactive_account_cannot_login(): void
    {
        $this->user('PUN002')->update(['status' => 'inactive']);
        $this->post('/login', ['nik' => 'PUN002', 'password' => 'Password123'])->assertSessionHasErrors('nik');
        $this->assertGuest();
    }

    public function test_forgot_password_sends_reset_link(): void
    {
        Notification::fake();
        $this->post(route('password.email'), ['nik' => 'PUN001', 'email' => 'ahmad.fauzi@puninar.com'])->assertSessionHas('success');
        Notification::assertSentTo($this->user('PUN001'), \Illuminate\Auth\Notifications\ResetPassword::class);
    }

    // ---- Semua halaman dapat dibuka ----

    public function test_all_member_pages_render(): void
    {
        $this->actingAs($this->user('PUN001'));
        foreach (['dashboard', 'savings.index', 'savings.create', 'savings.statement', 'loans.index', 'loans.create',
            'transactions.index', 'shu.index', 'notifications.index', 'profile', 'password.edit'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_all_admin_pages_render(): void
    {
        $this->apply('PUN003', 1000000, 6);  // pengajuan manual review
        $this->apply('PUN001', 3000000, 12); // pinjaman aktif
        $app = LoanApplication::first();
        $loan = ActiveLoan::first();

        $this->actingAs($this->user('ADMIN001'));
        foreach (['admin.dashboard', 'admin.users.index', 'admin.users.create', 'admin.loans.index', 'admin.active-loans.index',
            'admin.overdue.index', 'admin.verifications.index', 'admin.savings.index', 'admin.transactions.index',
            'admin.reports.index', 'admin.shu.index', 'admin.rules.index', 'admin.settings.index', 'profile', 'notifications.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('admin.loans.show', $app))->assertOk();
        $this->get(route('admin.active-loans.show', $loan))->assertOk();
        $this->get(route('admin.users.show', $this->user('PUN001')))->assertOk();
        $this->get(route('admin.rules.edit', \App\Models\Rule::first()))->assertOk()->assertSee('Ubah Aturan');
        $this->get(route('admin.shu.export', now()->year))->assertOk();
        $this->get(route('loans.index'))->assertForbidden();
    }

    // ---- Pengajuan pinjaman ----

    public function test_eligible_loan_is_auto_approved_and_disbursed(): void
    {
        $this->apply('PUN001', 3000000, 12)->assertSessionHas('success');

        $app = LoanApplication::first();
        $this->assertSame('disbursed', $app->status);
        $this->assertTrue($app->auto_approved);
        $this->assertEquals(295000, (float) $app->monthly_installment); // 250.000 + 45.000

        $loan = ActiveLoan::first();
        $this->assertSame(12, $loan->schedules()->count());
        $this->assertEquals(3000000, (float) $loan->schedules()->sum('principal_amount'));
        $this->assertDatabaseHas('transactions', ['type' => 'loan_disbursement', 'amount' => 3000000, 'related_loan_id' => $loan->id]);
    }

    public function test_loan_above_limit_is_rejected(): void
    {
        // PUN001: simpanan 3 jt x 2 = plafon 6 jt
        $this->apply('PUN001', 6000001, 12)->assertSessionHas('error');
        $this->assertSame(0, LoanApplication::count());
    }

    public function test_short_employment_is_not_eligible(): void
    {
        $this->apply('PUN004', 200000, 6)->assertSessionHas('error');
        $this->assertSame(0, LoanApplication::count());
        $this->assertStringContainsString('Masa kerja minimal 12 bulan', session('error'));

        // Halaman formulir juga menolak dan mengarahkan ke daftar pinjaman
        $this->get(route('loans.create'))->assertRedirect(route('loans.index'));
    }

    public function test_outsourcing_goes_to_manual_review_and_cannot_be_approved_twice(): void
    {
        $this->apply('PUN003', 1500000, 6);
        $app = LoanApplication::first();
        $this->assertSame('pending', $app->status);
        $this->assertSame('R16', $app->decision_rule_code);

        $admin = $this->user('HRD001');
        $this->actingAs($admin)->post(route('admin.loans.approve', $app))->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.loans.approve', $app))->assertSessionHas('error');

        $this->assertSame(1, ActiveLoan::count());
        $this->assertSame('disbursed', $app->fresh()->status);
    }

    // ---- Pembayaran angsuran ----

    public function test_payment_needs_verification_and_loan_completes_only_after_last_installment(): void
    {
        $this->apply('PUN001', 1200000, 3);
        $loan = ActiveLoan::first();
        $first = $loan->schedules()->first();

        $this->actingAs($this->user('PUN001'))->post(route('loans.pay'), ['schedule_id' => $first->id, 'payment_method' => 'transfer'])
            ->assertSessionHas('success');
        $this->assertSame('pending', $first->fresh()->status);
        $this->assertSame(3, $loan->fresh()->remaining_months);

        // Kirim ulang tidak boleh membuat transaksi ganda
        $this->post(route('loans.pay'), ['schedule_id' => $first->id, 'payment_method' => 'transfer'])->assertSessionHas('error');

        $tx = Transaction::where('type', 'loan_payment')->firstOrFail();
        $admin = $this->user('FIN001');
        $this->actingAs($admin)->post(route('admin.verifications.approve', $tx))->assertSessionHas('success');

        $loan->refresh();
        $this->assertSame('active', $loan->status, 'Pinjaman tidak boleh lunas setelah angsuran pertama');
        $this->assertSame(2, $loan->remaining_months);
        $this->assertEquals(800000, (float) $loan->remaining_balance);

        foreach ($loan->schedules()->where('status', '!=', 'paid')->get() as $s) {
            $this->actingAs($admin)->post(route('admin.schedules.pay', $s), ['payment_method' => 'payroll_deduction'])->assertSessionHas('success');
        }
        $loan->refresh();
        $this->assertSame('completed', $loan->status);
        $this->assertEquals(0, (float) $loan->remaining_balance);
        $this->assertNull($loan->next_due_date);
    }

    public function test_installments_must_be_paid_in_order(): void
    {
        $this->apply('PUN001', 1200000, 3);
        $second = ActiveLoan::first()->schedules()->where('installment_number', 2)->first();

        $this->actingAs($this->user('PUN001'))->post(route('loans.pay'), ['schedule_id' => $second->id, 'payment_method' => 'transfer'])
            ->assertSessionHas('error', 'Silakan bayar angsuran ke-1 terlebih dahulu.');
    }

    public function test_member_cannot_pay_someone_elses_installment(): void
    {
        $this->apply('PUN001', 1200000, 3);
        $schedule = ActiveLoan::first()->schedules()->first();

        $this->actingAs($this->user('PUN002'))->post(route('loans.pay'), ['schedule_id' => $schedule->id, 'payment_method' => 'transfer'])
            ->assertSessionHas('error');
        $this->assertSame(0, Transaction::where('type', 'loan_payment')->count());
    }

    public function test_late_fee_is_half_percent_per_day(): void
    {
        $this->apply('PUN001', 3000000, 12);
        $schedule = ActiveLoan::first()->schedules()->first();
        $schedule->update(['due_date' => today()->subDays(10)]);

        $this->assertEquals(14750, LoanService::lateFee($schedule->fresh())); // 295.000 x 0,5% x 10
        $this->assertSame(1, app(LoanService::class)->markOverdue());
    }

    // ---- Simpanan ----

    public function test_member_deposit_waits_for_admin_verification(): void
    {
        $member = $this->user('PUN002');
        $this->actingAs($member)->post(route('savings.store'), ['amount' => '100.000', 'payment_method' => 'transfer'])->assertRedirect();
        $this->assertEquals(4000000, (float) $member->savingsAccount->fresh()->balance);

        $tx = Transaction::where('status', 'pending')->firstOrFail();
        $this->actingAs($this->user('HRD001'))->post(route('admin.verifications.approve', $tx));
        $this->assertEquals(4100000, (float) $member->savingsAccount->fresh()->balance);
        $this->assertEquals(4100000, (float) $tx->fresh()->balance_after);
    }

    public function test_rejected_deposit_does_not_change_balance(): void
    {
        $member = $this->user('PUN002');
        $this->actingAs($member)->post(route('savings.store'), ['amount' => '100.000', 'payment_method' => 'transfer']);
        $tx = Transaction::where('status', 'pending')->firstOrFail();

        $this->actingAs($this->user('HRD001'))->post(route('admin.verifications.reject', $tx), ['reason' => 'Bukti tidak ada'])->assertSessionHas('success');
        $this->assertSame('failed', $tx->fresh()->status);
        $this->assertEquals(4000000, (float) $member->savingsAccount->fresh()->balance);
    }

    public function test_close_membership_refunds_savings(): void
    {
        $member = $this->user('PUN002');
        $this->actingAs($this->user('ADMIN001'))->post(route('admin.users.close', $member), ['payment_method' => 'transfer', 'confirm' => '1'])
            ->assertSessionHas('success');

        $this->assertSame('resigned', $member->fresh()->status);
        $this->assertDatabaseHas('transactions', ['user_id' => $member->id, 'type' => 'savings_withdrawal', 'amount' => 4000000]);
        $this->assertDatabaseHas('savings_accounts', ['user_id' => $member->id, 'status' => 'closed', 'balance' => 0]);
    }

    public function test_cannot_close_membership_with_active_loan(): void
    {
        $this->apply('PUN001', 1000000, 6);
        $this->actingAs($this->user('ADMIN001'))->post(route('admin.users.close', $this->user('PUN001')), ['payment_method' => 'cash', 'confirm' => '1'])
            ->assertSessionHas('error');
        $this->assertSame('active', $this->user('PUN001')->status);
    }

    public function test_admin_cannot_change_super_admin(): void
    {
        $this->actingAs($this->user('HRD001'))->post(route('admin.users.toggle', $this->user('ADMIN001')))->assertForbidden();
    }

    // ---- Notifikasi ----

    public function test_mark_read_does_not_delete_notification(): void
    {
        $member = $this->user('PUN001');
        $n = UserNotification::create(['user_id' => $member->id, 'title' => 'Tes', 'message' => 'Isi']);

        $this->actingAs($member)->post(route('notifications.read', $n))->assertSessionHas('success');
        $this->assertTrue($n->fresh()->is_read);

        $this->actingAs($this->user('PUN002'))->delete(route('notifications.destroy', $n))->assertNotFound();
        $this->actingAs($member)->delete(route('notifications.destroy', $n));
        $this->assertNull($n->fresh());
    }

    // ---- SHU, aturan, pengaturan ----

    public function test_shu_member_shares_add_up_to_pool(): void
    {
        $this->apply('PUN001', 3000000, 12);
        $admin = $this->user('ADMIN001');
        $schedule = ActiveLoan::first()->schedules()->first();
        $this->actingAs($admin)->post(route('admin.schedules.pay', $schedule), ['payment_method' => 'payroll_deduction']);

        $calc = app(ShuService::class)->calculate((int) now()->year, 0, 1000000);
        $this->assertEquals(1045000, $calc['shu_amount']); // bunga 45.000 + pendapatan lain 1 jt
        $pool = $calc['allocation']['jasa_simpanan'] + $calc['allocation']['jasa_pinjaman'];
        $this->assertEquals($pool, $calc['member_shu_total']);
        $this->assertEquals($calc['shu_amount'], array_sum(array_intersect_key($calc['allocation'], ShuService::POSTS)));

        $this->actingAs($admin)->post(route('admin.shu.save'), ['year' => now()->year, 'operating_expense' => '0', 'other_income' => '1.000.000', 'action' => 'final'])
            ->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.shu.save'), ['year' => now()->year, 'operating_expense' => '0', 'other_income' => '0', 'action' => 'draft'])
            ->assertSessionHas('error');

        $this->actingAs($this->user('PUN001'))->get(route('shu.index'))->assertOk()->assertSee('sudah final');
    }

    public function test_rule_can_be_created_and_simulated(): void
    {
        $admin = $this->user('ADMIN001');
        $this->actingAs($admin)->post(route('admin.rules.store'), [
            'rule_code' => 'R20', 'rule_group' => 'loan_eligibility', 'name' => 'Gaji terlalu kecil', 'conclusion' => 'reject',
            'message' => 'Gaji minimal Rp {nilai}', 'priority' => 5, 'is_active' => '1',
            'fact_key' => ['gaji_bulanan'], 'operator' => ['<'], 'value_type' => ['static'], 'compare_value' => ['1000000'], 'fallback_value' => [''],
        ])->assertSessionHas('success');

        $this->actingAs($admin)->post(route('admin.rules.simulate'), [
            'masa_kerja_bulan' => 24, 'status_karyawan' => 'active', 'kategori_anggota' => 'karyawan', 'potongan_hrd' => '1',
            'gaji_bulanan' => '500.000', 'simpanan_wajib' => '3.000.000', 'jumlah_pinjaman' => '1.000.000', 'tenor_bulan' => 12,
            'punya_pinjaman_aktif' => '0', 'punya_pengajuan_pending' => '0', 'riwayat_tunggakan' => 0,
        ])->assertSessionHas('simulation', fn ($s) => $s['eligibility']['rule_code'] === 'R20');

        $this->actingAs($admin)->get(route('admin.rules.index'))->assertSee('Gaji minimal Rp 1.000.000');
    }

    public function test_settings_validation_and_update(): void
    {
        $admin = $this->user('ADMIN001');
        $shu = ['shu_pct_cadangan' => 30, 'shu_pct_jasa_anggota' => 40, 'shu_pct_dana_pengurus' => 10, 'shu_pct_dana_karyawan' => 5,
            'shu_pct_dana_pendidikan' => 10, 'shu_pct_dana_sosial' => 10, 'shu_pct_jasa_simpanan' => 50, 'shu_pct_jasa_pinjaman' => 50];
        $this->actingAs($admin)->put(route('admin.settings.update', 'shu'), $shu)->assertSessionHas('error'); // total 105%

        $this->actingAs($admin)->put(route('admin.settings.update', 'auto_approve'), [
            'loan_auto_approve_max_amount' => '1.000.000', 'loan_auto_approve_max_term_months' => 12,
        ])->assertSessionHas('success'); // switch tidak dicentang = nonaktif

        $this->apply('PUN001', 500000, 6);
        $this->assertSame('pending', LoanApplication::first()->status);
        $this->assertSame('R10', LoanApplication::first()->decision_rule_code);
    }

    public function test_activity_is_logged(): void
    {
        $this->apply('PUN001', 500000, 6);
        $this->assertTrue(ActivityLog::where('action', 'loan_application_submitted')->exists());
    }
}
