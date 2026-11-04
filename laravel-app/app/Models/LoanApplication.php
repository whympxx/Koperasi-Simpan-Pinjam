<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LoanApplication extends Model
{
    public const STATUSES = [
        'pending' => 'Menunggu',
        'reviewing' => 'Direview',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'disbursed' => 'Dicairkan',
    ];

    protected $fillable = [
        'user_id', 'application_number', 'loan_amount', 'interest_rate', 'loan_term_months',
        'monthly_installment', 'total_payment', 'purpose', 'description', 'status', 'auto_approved',
        'decision_rule_code', 'reviewed_by', 'reviewed_at', 'review_notes', 'approved_by', 'approved_at',
        'disbursed_at', 'rejected_reason',
    ];

    protected function casts(): array
    {
        return [
            'loan_amount' => 'decimal:2',
            'interest_rate' => 'decimal:2',
            'monthly_installment' => 'decimal:2',
            'total_payment' => 'decimal:2',
            'auto_approved' => 'boolean',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'disbursed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function activeLoan(): HasOne
    {
        return $this->hasOne(ActiveLoan::class, 'application_id');
    }

    public function isReviewable(): bool
    {
        return in_array($this->status, ['pending', 'reviewing'], true);
    }
}
