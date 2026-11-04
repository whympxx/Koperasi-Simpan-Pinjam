<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Member;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route(auth()->user()->homeRoute()) : redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/lupa-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/lupa-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Fitur bersama semua peran
    Route::middleware('role:nasabah,admin,super_admin')->group(function () {
        Route::get('/profil', [ProfileController::class, 'show'])->name('profile');
        Route::get('/ubah-password', [ProfileController::class, 'editPassword'])->name('password.edit');
        Route::put('/ubah-password', [ProfileController::class, 'updatePassword'])->name('password.change');

        Route::get('/kwitansi/{transaction}', [ReceiptController::class, 'show'])->name('receipts.show');

        Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('/notifikasi/{notification}/baca', [NotificationController::class, 'read'])->name('notifications.read');
        Route::delete('/notifikasi/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    });

    // Nasabah (anggota)
    Route::middleware('role:nasabah')->group(function () {
        Route::get('/dashboard', [Member\DashboardController::class, 'index'])->name('dashboard');

        Route::get('/simpanan', [Member\SavingsController::class, 'index'])->name('savings.index');
        Route::get('/simpanan/setor', [Member\SavingsController::class, 'create'])->name('savings.create');
        Route::post('/simpanan/setor', [Member\SavingsController::class, 'store'])->name('savings.store');
        Route::get('/simpanan/rekening-koran', [Member\SavingsController::class, 'statement'])->name('savings.statement');

        Route::get('/pinjaman', [Member\LoanController::class, 'index'])->name('loans.index');
        Route::get('/pinjaman/ajukan', [Member\LoanController::class, 'create'])->name('loans.create');
        Route::post('/pinjaman/ajukan', [Member\LoanController::class, 'store'])->name('loans.store');
        Route::get('/pinjaman/bayar', [Member\LoanController::class, 'payment'])->name('loans.payment');
        Route::post('/pinjaman/bayar', [Member\LoanController::class, 'pay'])->name('loans.pay');

        Route::get('/transaksi', [Member\TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/shu', [Member\ShuController::class, 'index'])->name('shu.index');
    });

    // Admin (pengurus)
    Route::middleware('role:admin,super_admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        Route::get('/anggota', [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('/anggota/tambah', [Admin\UserController::class, 'create'])->name('users.create');
        Route::post('/anggota', [Admin\UserController::class, 'store'])->name('users.store');
        Route::get('/anggota/{user}', [Admin\UserController::class, 'show'])->name('users.show');
        Route::put('/anggota/{user}', [Admin\UserController::class, 'update'])->name('users.update');
        Route::post('/anggota/{user}/status', [Admin\UserController::class, 'toggleStatus'])->name('users.toggle');
        Route::post('/anggota/{user}/setoran', [Admin\UserController::class, 'deposit'])->name('users.deposit');
        Route::post('/anggota/{user}/keluar', [Admin\UserController::class, 'closeMembership'])->name('users.close');

        Route::get('/pengajuan', [Admin\LoanApplicationController::class, 'index'])->name('loans.index');
        Route::get('/pengajuan/{application}', [Admin\LoanApplicationController::class, 'show'])->name('loans.show');
        Route::post('/pengajuan/{application}/setujui', [Admin\LoanApplicationController::class, 'approve'])->name('loans.approve');
        Route::post('/pengajuan/{application}/tolak', [Admin\LoanApplicationController::class, 'reject'])->name('loans.reject');

        Route::get('/pinjaman-aktif', [Admin\ActiveLoanController::class, 'index'])->name('active-loans.index');
        Route::get('/pinjaman-aktif/{loan}', [Admin\ActiveLoanController::class, 'show'])->name('active-loans.show');
        Route::post('/pinjaman-aktif/{loan}/pelunasan', [Admin\ActiveLoanController::class, 'payoff'])->name('active-loans.payoff');
        Route::post('/angsuran/{schedule}/bayar', [Admin\ActiveLoanController::class, 'recordPayment'])->name('schedules.pay');

        Route::get('/potong-gaji', [Admin\PayrollController::class, 'index'])->name('payroll.index');
        Route::post('/potong-gaji', [Admin\PayrollController::class, 'process'])->name('payroll.process');

        Route::get('/pengumuman', [Admin\AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('/pengumuman', [Admin\AnnouncementController::class, 'store'])->name('announcements.store');
        Route::put('/pengumuman/{announcement}', [Admin\AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('/pengumuman/{announcement}', [Admin\AnnouncementController::class, 'destroy'])->name('announcements.destroy');

        Route::get('/log-aktivitas', [Admin\ActivityLogController::class, 'index'])->name('activity-logs.index');

        Route::get('/tunggakan', [Admin\OverdueController::class, 'index'])->name('overdue.index');
        Route::post('/tunggakan/{schedule}/ingatkan', [Admin\OverdueController::class, 'remind'])->name('overdue.remind');

        Route::get('/verifikasi', [Admin\VerificationController::class, 'index'])->name('verifications.index');
        Route::post('/verifikasi/{transaction}/terima', [Admin\VerificationController::class, 'approve'])->name('verifications.approve');
        Route::post('/verifikasi/{transaction}/tolak', [Admin\VerificationController::class, 'reject'])->name('verifications.reject');

        Route::get('/simpanan', [Admin\SavingsAccountController::class, 'index'])->name('savings.index');
        Route::get('/transaksi', [Admin\TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/laporan', [Admin\ReportController::class, 'index'])->name('reports.index');

        Route::get('/shu', [Admin\ShuController::class, 'index'])->name('shu.index');
        Route::post('/shu', [Admin\ShuController::class, 'save'])->name('shu.save');
        Route::post('/shu/{year}/buka', [Admin\ShuController::class, 'reopen'])->whereNumber('year')->name('shu.reopen');
        Route::get('/shu/{year}/csv', [Admin\ShuController::class, 'export'])->whereNumber('year')->name('shu.export');

        Route::get('/aturan', [Admin\RuleController::class, 'index'])->name('rules.index');
        Route::post('/aturan', [Admin\RuleController::class, 'store'])->name('rules.store');
        Route::get('/aturan/{rule}/ubah', [Admin\RuleController::class, 'edit'])->name('rules.edit');
        Route::put('/aturan/{rule}', [Admin\RuleController::class, 'update'])->name('rules.update');
        Route::post('/aturan/{rule}/status', [Admin\RuleController::class, 'toggle'])->name('rules.toggle');
        Route::delete('/aturan/{rule}', [Admin\RuleController::class, 'destroy'])->name('rules.destroy');
        Route::post('/aturan/simulasi', [Admin\RuleController::class, 'simulate'])->name('rules.simulate');

        Route::get('/pengaturan', [Admin\SettingController::class, 'index'])->name('settings.index');
        Route::put('/pengaturan/{section}', [Admin\SettingController::class, 'update'])->whereIn('section', ['general', 'auto_approve', 'shu'])->name('settings.update');
    });
});
