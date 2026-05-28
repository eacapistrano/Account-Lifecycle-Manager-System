<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\EvaluatePoliciesJob;
use App\Jobs\ImportStudentsJob;
use App\Jobs\ProcessSuspendedDueDatesJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AutomationQueueController extends Controller
{
    public function index(): JsonResponse
    {
        $connection = (string) config('queue.default');
        $jobsTable = config("queue.connections.{$connection}.table", 'jobs');
        $failedTable = config('queue.failed.table', 'failed_jobs');

        $pending = (int) DB::table($jobsTable)->count();
        $failed = (int) DB::table($failedTable)->count();

        $recentPending = DB::table($jobsTable)
            ->orderByDesc('id')
            ->limit(15)
            ->get(['id', 'queue', 'payload', 'attempts', 'reserved_at', 'available_at', 'created_at'])
            ->map(function (object $row): array {
                return [
                    'id' => $row->id,
                    'queue' => $row->queue,
                    'job_name' => $this->resolveJobNameFromPayload((string) ($row->payload ?? '')),
                    'attempts' => $row->attempts,
                    'status' => $row->reserved_at !== null ? 'processing' : 'queued',
                    'available_at' => $this->timestampToIso($row->available_at),
                    'created_at' => $this->timestampToIso($row->created_at),
                ];
            })
            ->all();

        $recentFailed = DB::table($failedTable)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'uuid', 'queue', 'failed_at'])
            ->map(function (object $row): array {
                return [
                    'id' => $row->id,
                    'uuid' => $row->uuid,
                    'queue' => $row->queue,
                    'failed_at' => $this->timestampToIso($row->failed_at),
                ];
            })
            ->all();

        return response()->json([
            'queue_connection' => $connection,
            'pending_count' => $pending,
            'failed_count' => $failed,
            'google_workspace' => $this->googleWorkspaceStatus(),
            'schedules' => $this->scheduledTasks(),
            'recent_pending' => $recentPending,
            'recent_failed' => $recentFailed,
        ]);
    }

    public function dispatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'task' => ['required', 'string', Rule::in([
                'policy_evaluation',
                'suspended_due_dates',
            ])],
        ]);

        match ($data['task']) {
            'policy_evaluation' => EvaluatePoliciesJob::dispatch(),
            'suspended_due_dates' => ProcessSuspendedDueDatesJob::dispatch(),
        };

        return response()->json([
            'queued' => true,
            'task' => $data['task'],
        ], 202);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scheduledTasks(): array
    {
        $tasks = [
            [
                'key' => 'policy_evaluation',
                'name' => 'Policy evaluation',
                'cron' => (string) config('automation.schedule.policy_evaluation_cron', '*/15 * * * *'),
                'description' => 'Evaluates active policies (including graduation warnings and suspends).',
            ],
            [
                'key' => 'suspended_due_dates',
                'name' => 'Suspended due-date deletion',
                'cron' => (string) config('automation.schedule.suspended_due_date_cron', '*/15 * * * *'),
                'description' => 'Deletes accounts past their scheduled deletion date.',
            ],
        ];

        if (config('student_import.enabled')) {
            $tasks[] = [
                'key' => 'student_import',
                'name' => 'Student registry import',
                'cron' => (string) config('student_import.schedule_cron', '0 2 * * *'),
                'description' => 'Imports students from the configured source database.',
            ];
        }

        return $tasks;
    }

    /**
     * @return array<string, mixed>
     */
    private function googleWorkspaceStatus(): array
    {
        $credentialsPath = (string) config('google_workspace.credentials_path');
        $resolvedCredentialsPath = $this->resolveReadablePath($credentialsPath);
        $scopes = config('google_workspace.scopes', []);
        $impersonateEmail = trim((string) config('google_workspace.impersonate_email'));

        return [
            'suspend_enabled' => (bool) config('google_workspace.suspend_enabled'),
            'suspend_dry_run' => (bool) config('google_workspace.suspend_dry_run'),
            'delete_enabled' => (bool) config('google_workspace.delete_enabled'),
            'delete_dry_run' => (bool) config('security.student_delete_dry_run'),
            'credentials_configured' => $credentialsPath !== '',
            'credentials_readable' => $resolvedCredentialsPath !== null,
            'impersonation_configured' => $impersonateEmail !== '',
            'impersonate_email' => $impersonateEmail !== '' ? $impersonateEmail : null,
            'scopes' => is_array($scopes) ? array_values($scopes) : [],
            'suspend_user_key' => (string) config('google_workspace.suspend_user_key'),
            'delete_user_key' => (string) config('google_workspace.delete_user_key'),
            'ready_for_suspend' => (bool) config('google_workspace.suspend_enabled')
                && ! (bool) config('google_workspace.suspend_dry_run')
                && $resolvedCredentialsPath !== null
                && $impersonateEmail !== '',
            'ready_for_delete' => (bool) config('google_workspace.delete_enabled')
                && ! (bool) config('security.student_delete_dry_run')
                && $resolvedCredentialsPath !== null
                && $impersonateEmail !== '',
        ];
    }

    private function resolveReadablePath(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        if (is_file($path) && is_readable($path)) {
            return $path;
        }

        $relativeToBase = base_path($path);
        if (is_file($relativeToBase) && is_readable($relativeToBase)) {
            return $relativeToBase;
        }

        return null;
    }

    private function resolveJobNameFromPayload(string $payload): string
    {
        if ($payload === '') {
            return 'unknown';
        }

        if (str_contains($payload, EvaluatePoliciesJob::class)) {
            return 'EvaluatePoliciesJob';
        }
        if (str_contains($payload, ProcessSuspendedDueDatesJob::class)) {
            return 'ProcessSuspendedDueDatesJob';
        }
        if (str_contains($payload, ImportStudentsJob::class)) {
            return 'ImportStudentsJob';
        }

        return 'queued_job';
    }

    private function timestampToIso(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return now()->createFromTimestamp((int) $value)->toIso8601String();
        }

        return (string) $value;
    }
}
