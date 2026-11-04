<?php

namespace Tests\Feature;

use App\Models\ActiveLoan;
use App\Models\Announcement;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ShuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KoperasiFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function user(string $nik): User
    {
        return User::where('nik', $nik)->firstOrFail();
    }

    private function loanFor(string $nik, int $amount, int $term): ActiveLoan
    {
        $this->actingAs($this->user($nik))->post(route('loans.store'), [
            'loan_amount' => $amount, 'term_months' => $term, 'purpose' => 'Pendidikan', 'agreement' => '1',
        ]);

        return ActiveLoan::where('user_id', $this->user($nik)->id)->firstOrFail();
    }

    public function test_new_pages_render(): void
    {
        $loan = $this->loanFor('PUN001', 1200000, 6);
        $this->actingAs($this->user('ADMIN001'));
        foreach (['admin.payroll.index', 'admin.announcements.index', 'admin.activity-logs.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
        $this->get(route('admin.active-loans.show', $loan))->assertOk()->assertSee('Pelunasan Dipercepat');
        $this->get(route('login'))->assertRedirect(); // sudah login

        auth()->logout();
        $this->get(route('login'))->assertOk()->assertSee('hero3d', false)->assertSee('three.min.js', false);
        $this->actingAs($this->user('PUN001'))->get(route('profile'))->assertOk()->assertSee('member-card-scene', false);
        $this->get(route('loans.index'))->assertOk()->assertSee('Simulasi Pelunasan Dipercepat');
    }

    public function test_early_payoff_waives_future_interest(): void
    {
        $loan = $this->loanFor('PUN001', 1200000, 6); // pokok 200.000/bln, bunga 18.000/bln
        $this->actingAs($this->user('FIN001'))->post(route('admin.active-loans.payoff', $loan), ['payment_method' => 'transfer'])
            ->assertSessionHas('success');

        $loan->refresh();
        $this->assertSame('completed', $loan->status);
        $this->assertEquals(0, (float) $loan->remaining_balance);
        // Angsuran pertama (berjalan) dibayar penuh, 5 sisanya hanya pokok.
        $this->assertDatabaseHas('transactions', ['related_loan_id' => $loan->id, 'type' => 'loan_payment', 'amount' => 1218000]);
        $this->assertEquals(18000, (float) $loan->schedules()->sum('interest_amount'));
        $this->assertSame(0, $loan->schedules()->where('status', '!=', 'paid')->count());

        // Pendapatan SHU hanya menghitung bunga yang benar-benar ditagih.
        $this->assertEquals(18000, app(ShuService::class)->calculate((int) now()->year)['interest_income']);

        // Nasabah langsung boleh mengajukan lagi.
        $this->actingAs($this->user('PUN001'))->get(route('loans.create'))->assertOk();
    }

    public function test_payroll_batch_posts_dues_and_installments_once(): void
    {
        $loan = $this->loanFor('PUN001', 1200000, 6);
        $loan->schedules()->first()->update(['due_date' => now()->startOfMonth()]);
        $admin = $this->user('HRD001');
        $period = now()->format('Y-m');

        $ids = [$this->user('PUN001')->id, $this->user('PUN002')->id, $this->user('PUN003')->id];
        $this->actingAs($admin)->post(route('admin.payroll.process'), ['period' => $period, 'users' => $ids])->assertSessionHas('success');

        // PUN001: iuran 50.000 + angsuran 218.000; PUN002: iuran saja; PUN003 outsourcing: dilewati
        $this->assertEquals(3050000, (float) $this->user('PUN001')->savingsAccount->balance);
        $this->assertEquals(4050000, (float) $this->user('PUN002')->savingsAccount->balance);
        $this->assertEquals(1500000, (float) $this->user('PUN003')->savingsAccount->balance);
        $this->assertSame('paid', $loan->schedules()->first()->status);

        // Diproses ulang: tidak ada pembukuan ganda
        $this->actingAs($admin)->post(route('admin.payroll.process'), ['period' => $period, 'users' => $ids]);
        $this->assertEquals(3050000, (float) $this->user('PUN001')->savingsAccount->fresh()->balance);
        $this->assertSame(1, Transaction::where('type', 'loan_payment')->count());
    }

    public function test_receipt_only_for_owner_or_admin_and_completed(): void
    {
        $member = $this->user('PUN001');
        $tx = Transaction::where('user_id', $member->id)->where('status', 'completed')->firstOrFail();

        $this->actingAs($member)->get(route('receipts.show', $tx))->assertOk()->assertSee($tx->reference_number)->assertSee('LUNAS');
        $this->actingAs($this->user('ADMIN001'))->get(route('receipts.show', $tx))->assertOk();
        $this->actingAs($this->user('PUN002'))->get(route('receipts.show', $tx))->assertNotFound();

        $this->actingAs($member)->post(route('savings.store'), ['amount' => '100.000', 'payment_method' => 'transfer']);
        $pending = Transaction::where('status', 'pending')->firstOrFail();
        $this->get(route('receipts.show', $pending))->assertNotFound();
    }

    public function test_announcements_visibility_and_notification(): void
    {
        $admin = $this->user('ADMIN001');
        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Undangan RAT 2026', 'body' => 'RAT diadakan di aula kantor.', 'category' => 'rat', 'is_published' => '1', 'notify' => '1',
        ])->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'Draf internal', 'body' => 'Belum terbit.', 'category' => 'info',
        ]);

        $this->assertTrue($this->user('PUN002')->appNotifications()->where('title', 'like', '%Undangan RAT%')->exists());

        $this->actingAs($this->user('PUN002'))->get(route('dashboard'))->assertSee('Undangan RAT 2026')->assertDontSee('Draf internal');

        $ann = Announcement::where('title', 'Draf internal')->first();
        $this->actingAs($admin)->put(route('admin.announcements.update', $ann), ['title' => $ann->title, 'body' => $ann->body, 'category' => 'info', 'is_published' => '1']);
        $this->actingAs($this->user('PUN002'))->get(route('dashboard'))->assertSee('Draf internal');
    }

    public function test_activity_log_page_filters(): void
    {
        $this->post('/login', ['nik' => 'PUN001', 'password' => 'salah']);
        $this->actingAs($this->user('ADMIN001'))->get(route('admin.activity-logs.index', ['action' => 'failed_login']))
            ->assertOk()->assertSee('failed login');
        $this->actingAs($this->user('PUN001'))->get(route('admin.activity-logs.index'))->assertForbidden();
    }
}
