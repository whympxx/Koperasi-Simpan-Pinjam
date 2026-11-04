<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleEvaluationLog extends Model
{
    protected $fillable = ['user_id', 'rule_group', 'entity_type', 'entity_id', 'decision', 'fired_rule_code', 'facts', 'trace'];

    protected function casts(): array
    {
        return ['facts' => 'array', 'trace' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
