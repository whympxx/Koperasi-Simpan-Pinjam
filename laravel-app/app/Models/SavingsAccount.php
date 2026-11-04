<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingsAccount extends Model
{
    protected $fillable = [
        'user_id', 'account_number', 'balance', 'status', 'opened_date', 'closed_date', 'last_transaction_at',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'opened_date' => 'date',
            'closed_date' => 'date',
            'last_transaction_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'related_account_id');
    }
}
