<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShuMemberShare extends Model
{
    protected $guarded = ['id'];

    public function period(): BelongsTo
    {
        return $this->belongsTo(ShuPeriod::class, 'period_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
