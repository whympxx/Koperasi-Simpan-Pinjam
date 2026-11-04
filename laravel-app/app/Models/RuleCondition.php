<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleCondition extends Model
{
    protected $fillable = ['rule_id', 'fact_key', 'operator', 'value_type', 'compare_value', 'fallback_value', 'sort_order'];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(Rule::class);
    }
}
