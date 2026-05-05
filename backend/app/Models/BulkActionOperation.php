<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'operation_id',
    'action',
    'status',
    'total',
    'processed',
    'ok',
    'failed',
    'actor_user_id',
    'requested_at',
    'started_at',
    'completed_at',
    'error',
])]
class BulkActionOperation extends Model
{
    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'processed' => 'integer',
            'ok' => 'integer',
            'failed' => 'integer',
            'requested_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
