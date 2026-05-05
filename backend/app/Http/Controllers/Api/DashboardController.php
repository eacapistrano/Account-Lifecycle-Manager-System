<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\Policy;
use App\Models\Student;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $recent = AuditEvent::query()
            ->latest('id')
            ->limit(10)
            ->get(['id', 'module', 'action', 'success', 'created_at', 'target_account_id']);

        $policies = Policy::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'name', 'last_status', 'execution_at']);

        return response()->json([
            'counts' => [
                'students' => Student::query()->count(),
                'suspended' => Student::query()->where('suspended', true)->count(),
                'due_for_deletion' => Student::query()
                    ->where('suspended', true)
                    ->whereNotNull('deletion_scheduled_at')
                    ->where('deletion_scheduled_at', '<=', now())
                    ->count(),
                'active_policies' => Policy::query()->where('is_active', true)->count(),
            ],
            'recent_audit' => $recent,
            'active_policies' => $policies,
        ]);
    }
}
