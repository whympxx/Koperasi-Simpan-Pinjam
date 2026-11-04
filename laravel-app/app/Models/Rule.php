<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rule extends Model
{
    protected $fillable = [
        'rule_code', 'rule_group', 'name', 'description', 'conclusion', 'message', 'priority',
        'is_active', 'is_system', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_system' => 'boolean', 'priority' => 'integer'];
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(RuleCondition::class)->orderBy('sort_order')->orderBy('id');
    }
}
