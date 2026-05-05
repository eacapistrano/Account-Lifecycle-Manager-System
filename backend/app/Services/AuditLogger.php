<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    public function record(
        string $module,
        string $action,
        ?string $targetAccountId,
        array $payload,
        bool $success = true,
        ?string $errorMessage = null,
        ?User $actor = null,
        ?Request $request = null,
    ): AuditEvent {
        $req = $request ?? request();
        $correlation = $req?->attributes->get('correlation_id');

        return AuditEvent::query()->create([
            'actor_user_id' => $actor?->id ?? $req?->user()?->id,
            'module' => $module,
            'action' => $action,
            'target_account_id' => $targetAccountId,
            'payload' => $payload,
            'correlation_id' => is_string($correlation) ? $correlation : (string) Str::uuid(),
            'ip_address' => $req?->ip(),
            'success' => $success,
            'error_message' => $errorMessage,
        ]);
    }
}
