<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'action',
    'rule_json',
    'execution_at',
    'cron_expression',
    'is_active',
    'last_evaluated_at',
    'last_status',
    'hold_reason',
])]
class Policy extends Model
{
    protected function casts(): array
    {
        return [
            'rule_json' => 'array',
            'execution_at' => 'datetime',
            'is_active' => 'boolean',
            'last_evaluated_at' => 'datetime',
        ];
    }
}
