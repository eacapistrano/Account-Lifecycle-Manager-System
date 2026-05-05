<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'actor_user_id',
    'module',
    'action',
    'target_account_id',
    'payload',
    'correlation_id',
    'ip_address',
    'success',
    'error_message',
])]
class AuditEvent extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): bool {
            return false;
        });

        static::deleting(function (): bool {
            return false;
        });
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'correlation_id' => 'string',
            'success' => 'boolean',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
