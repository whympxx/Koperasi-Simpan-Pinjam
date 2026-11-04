<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    public const TYPES = [
        'savings_deposit' => 'Setoran Simpanan',
        'savings_withdrawal' => 'Penarikan Simpanan',
        'loan_disbursement' => 'Pencairan Pinjaman',
        'loan_payment' => 'Pembayaran Angsuran',
        'interest_credit' => 'Bunga Simpanan',
        'fee_debit' => 'Biaya/Denda',
    ];

    public const METHODS = [
        'transfer' => 'Transfer Bank',
        'cash' => 'Tunai',
        'payroll_deduction' => 'Potong Gaji (HRD)',
        'online' => 'Online',
    ];

    protected $fillable = [
        'user_id', 'reference_number', 'type', 'amount', 'description', 'related_account_id',
        'related_loan_id', 'related_schedule_id', 'balance_before', 'balance_after', 'payment_method',
        'processed_by', 'status', 'notes', 'verified_at', 'transaction_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'transaction_date' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(LoanSchedule::class, 'related_schedule_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(ActiveLoan::class, 'related_loan_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isCredit(): bool
    {
        return in_array($this->type, ['savings_deposit', 'interest_credit', 'loan_disbursement'], true);
    }
}
