<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    public const CATEGORIES = [
        'karyawan' => 'Karyawan',
        'driver' => 'Driver / Pengemudi',
        'outsourcing' => 'Outsourcing / Yayasan (angsur mandiri)',
    ];

    public const ROLES = [
        'nasabah' => 'Nasabah',
        'admin' => 'Admin',
        'super_admin' => 'Super Admin',
    ];

    public const STATUSES = [
        'active' => 'Aktif',
        'inactive' => 'Nonaktif',
        'resigned' => 'Keluar',
    ];

    protected $fillable = [
        'nik', 'full_name', 'email', 'phone', 'password', 'role', 'division_id', 'position',
        'member_category', 'payroll_deduction', 'employment_date', 'monthly_salary', 'status',
        'address', 'is_verified', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'payroll_deduction' => 'boolean',
            'is_verified' => 'boolean',
            'employment_date' => 'date',
            'monthly_salary' => 'decimal:2',
            'last_login_at' => 'datetime',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function savingsAccount(): HasOne
    {
        return $this->hasOne(SavingsAccount::class)->where('status', 'active');
    }

    public function loanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function activeLoan(): HasOne
    {
        return $this->hasOne(ActiveLoan::class)->where('status', 'active');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isNasabah(): bool
    {
        return $this->role === 'nasabah';
    }

    /** Outsourcing atau tanpa potongan HRD => plafon terbatas (1x). */
    public function hasPayrollDeduction(): bool
    {
        return $this->member_category !== 'outsourcing' && (bool) $this->payroll_deduction;
    }

    /** Masa kerja dalam bulan; tanggal kosong dihitung 0 agar aturan masa kerja tetap berlaku. */
    public function employmentMonths(): int
    {
        if (! $this->employment_date) {
            return 0;
        }

        $diff = $this->employment_date->diff(now());

        return $diff->invert ? 0 : $diff->y * 12 + $diff->m;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->member_category] ?? 'Karyawan';
    }

    public function homeRoute(): string
    {
        return $this->isAdmin() ? 'admin.dashboard' : 'dashboard';
    }
}
