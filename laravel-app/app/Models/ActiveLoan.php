<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActiveLoan extends Model
{
    protected $fillable = [
        'user_id', 'application_id', 'loan_number', 'original_amount', 'remaining_balance',
        'monthly_installment', 'interest_rate', 'loan_term_months', 'remaining_months', 'start_date',
        'next_due_date', 'status', 'total_paid', 'late_fees',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'remaining_balance' => 'decimal:2',
            'monthly_installment' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'late_fees' => 'decimal:2',
            'start_date' => 'date',
            'next_due_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(LoanApplication::class, 'application_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(LoanSchedule::class, 'loan_id')->orderBy('installment_number');
    }
}
