<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanSchedule extends Model
{
    protected $fillable = [
        'loan_id', 'installment_number', 'due_date', 'principal_amount', 'interest_amount', 'total_amount',
        'paid_amount', 'paid_date', 'late_fee', 'status', 'payment_transaction_id', 'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_date' => 'date',
            'principal_amount' => 'decimal:2',
            'interest_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'late_fee' => 'decimal:2',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(ActiveLoan::class, 'loan_id');
    }

    /** Angsuran yang belum dibayar dan sudah lewat jatuh tempo. */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', '!=', 'paid')->whereDate('due_date', '<', today());
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('status', '!=', 'paid');
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->due_date->lt(today());
    }

    public function daysLate(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(today()) : 0;
    }
}
