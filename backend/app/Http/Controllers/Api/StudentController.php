<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ImportStudentsJob;
use App\Jobs\ProcessBulkAccountActionJob;
use App\Models\AuditEvent;
use App\Models\BulkActionOperation;
use App\Models\Student;
use App\Services\StudentAccountLifecycleService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    private const TRACKER_TTL_SECONDS = 86400;

    public function __construct(
        protected AuditLogger $audit,
    ) {}

    public function index(Request $request)
    {
        $data = $request->validate([
            'email' => ['nullable', 'string', 'max:200'],
            'search' => ['nullable', 'string', 'max:200'],
            'graduation_status' => ['nullable', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $q = Student::query()->orderBy('primary_email');
        if (! empty($data['email'])) {
            $needle = '%'.addcslashes(strtolower(trim($data['email'])), '%_\\').'%';
            $q->whereRaw('LOWER(primary_email) LIKE ?', [$needle]);
        }
        if (! empty($data['search'])) {
            $q->searchAll($data['search']);
        }
        if (! empty($data['graduation_status'])) {
            $q->where('graduation_status', $data['graduation_status']);
        }

        return response()->json(['data' => $q->paginate($data['per_page'] ?? 50)]);
    }

    public function import(Request $request)
    {
        if (! config('student_import.enabled')) {
            return response()->json([
                'message' => 'Student import is disabled. Set STUDENT_IMPORT_ENABLED=true and configure SOURCE_DB_* and student_import.column_map.',
            ], 503);
        }

        ImportStudentsJob::dispatch(
            $request->user()?->id,
            (string) $request->attributes->get('correlation_id'),
        );

        return response()->json([
            'queued' => true,
            'source' => 'database',
        ], 202);
    }

    /** @obsolete Use POST /students/import */
    public function sync(Request $request)
    {
        return $this->import($request);
    }

    public function suspend(Request $request)
    {
        $max = (int) config('security.bulk_account_ids_max', 500);
        $data = $request->validate([
            'account_ids' => ['sometimes', 'array', 'max:'.$max],
            'account_ids.*' => ['required_with:account_ids', 'string', 'max:255'],
            'google_ids' => ['sometimes', 'array', 'max:'.$max],
            'google_ids.*' => ['required_with:google_ids', 'string', 'max:255'],
        ]);

        $ids = $data['account_ids'] ?? $data['google_ids'] ?? [];
        if ($ids === []) {
            throw ValidationException::withMessages([
                'account_ids' => ['The account ids field is required (or use legacy google_ids).'],
            ]);
        }

        $operationId = (string) Str::uuid();
        $this->initializeOperationTracker(
            $operationId,
            'suspend',
            count($ids),
            $request->user()?->id,
        );

        ProcessBulkAccountActionJob::dispatch(
            'suspend',
            $ids,
            $operationId,
            $request->user()?->id,
            $request->ip(),
            (string) $request->attributes->get('correlation_id'),
        );

        return response()->json([
            'queued' => true,
            'action' => 'suspend',
            'count' => count($ids),
            'operation_id' => $operationId,
        ], 202);
    }

   public function unsuspend(Request $request, StudentAccountLifecycleService $lifecycle)
{
    $max = (int) config('security.bulk_account_ids_max', 500);

    $data = $request->validate([
        'account_ids' => ['sometimes', 'array', 'max:'.$max],
        'account_ids.*' => ['required_with:account_ids', 'string', 'max:255'],
        'google_ids' => ['sometimes', 'array', 'max:'.$max],
        'google_ids.*' => ['required_with:google_ids', 'string', 'max:255'],
    ]);

    $ids = $data['account_ids'] ?? $data['google_ids'] ?? [];

    if ($ids === []) {
        throw ValidationException::withMessages([
            'account_ids' => ['The account ids field is required.'],
        ]);
    }

    $failures = [];

    foreach ($ids as $accountId) {
        try {
            $student = \App\Models\Student::where('external_account_id', $accountId)
                ->orWhere('primary_email', $accountId)
                ->firstOrFail();

            $lifecycle->unsuspendByPrimaryEmail($student->primary_email);

        } catch (\Throwable $e) {
            $failures[] = [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ];
        }
    }

    return response()->json([
        'queued' => false,
        'action' => 'unsuspend',
        'count' => count($ids),
        'ok' => count($ids) - count($failures),
        'failed' => count($failures),
        'failures' => $failures,
    ], 200);
}

    public function delete(Request $request)
    {
        $max = (int) config('security.bulk_account_ids_max', 500);
        $data = $request->validate([
            'account_ids' => ['sometimes', 'array', 'max:'.$max],
            'account_ids.*' => ['required_with:account_ids', 'string', 'max:255'],
            'google_ids' => ['sometimes', 'array', 'max:'.$max],
            'google_ids.*' => ['required_with:google_ids', 'string', 'max:255'],
            'confirmation_phrase' => ['required', 'string'],
        ]);

        if ($data['confirmation_phrase'] !== config('security.delete_confirmation_phrase')) {
            return response()->json([
                'message' => 'Confirmation phrase does not match configured DELETE_CONFIRMATION_PHRASE.',
            ], 422);
        }

        $ids = $data['account_ids'] ?? $data['google_ids'] ?? [];
        if ($ids === []) {
            throw ValidationException::withMessages([
                'account_ids' => ['The account ids field is required (or use legacy google_ids).'],
            ]);
        }

        $operationId = (string) Str::uuid();
        $this->initializeOperationTracker(
            $operationId,
            'delete',
            count($ids),
            $request->user()?->id,
        );

        ProcessBulkAccountActionJob::dispatch(
            'delete',
            $ids,
            $operationId,
            $request->user()?->id,
            $request->ip(),
            (string) $request->attributes->get('correlation_id'),
        );

        return response()->json([
            'queued' => true,
            'action' => 'delete',
            'count' => count($ids),
            'operation_id' => $operationId,
            'dry_run' => config('security.student_delete_dry_run'),
        ], 202);
    }

    public function operationStatus(string $operationId)
    {
        $status = Cache::get($this->trackerKey($operationId));
        if (is_array($status)) {
            return response()->json($status);
        }

        $operation = BulkActionOperation::query()
            ->where('operation_id', $operationId)
            ->first();
        if (! $operation) {
            return response()->json(['message' => 'Operation status not found.'], 404);
        }

        return response()->json($this->toStatusPayload($operation));
    }

    public function operationHistory(Request $request)
    {
        $data = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'action' => ['sometimes', 'in:suspend,delete'],
            'status' => ['sometimes', 'in:queued,running,completed,failed'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $query = BulkActionOperation::query()
            ->with('actor:id,name,email')
            ->orderByDesc('requested_at')
            ->orderByDesc('id');

        if (! empty($data['action'])) {
            $query->where('action', $data['action']);
        }
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['from'])) {
            $query->whereDate('requested_at', '>=', $data['from']);
        }
        if (! empty($data['to'])) {
            $query->whereDate('requested_at', '<=', $data['to']);
        }

        $history = $query->paginate($data['per_page'] ?? 20);

        $history->getCollection()->transform(fn (BulkActionOperation $operation): array => [
            ...$this->toStatusPayload($operation),
            'actor' => $operation->actor
                ? [
                    'name' => $operation->actor->name,
                    'email' => $operation->actor->email,
                ]
                : null,
        ]);

        return response()->json(['data' => $history]);
    }

    public function operationFailures(string $operationId, Request $request)
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        $operation = BulkActionOperation::query()
            ->where('operation_id', $operationId)
            ->first();
        if (! $operation) {
            return response()->json(['message' => 'Operation not found.'], 404);
        }

        $failures = AuditEvent::query()
            ->where('module', 'student_deletion')
            ->where('action', 'student.'.$operation->action)
            ->where('success', false)
            ->where('payload->operation_id', $operationId)
            ->orderByDesc('id')
            ->paginate((int) ($request->input('per_page', 100)));

        $failures->getCollection()->transform(static fn (AuditEvent $event): array => [
            'id' => $event->id,
            'target_account_id' => $event->target_account_id,
            'error_message' => $event->error_message,
            'created_at' => $event->created_at?->toIso8601String(),
            'correlation_id' => $event->correlation_id,
            'payload' => $event->payload,
        ]);

        return response()->json([
            'data' => [
                'operation' => [
                    'operation_id' => $operation->operation_id,
                    'action' => $operation->action,
                    'status' => $operation->status,
                    'requested_at' => $operation->requested_at?->toIso8601String(),
                    'failed' => $operation->failed,
                ],
                'failures' => $failures,
            ],
        ]);
    }

    private function initializeOperationTracker(
        string $operationId,
        string $action,
        int $total,
        ?int $actorUserId,
    ): void {
        $now = now();

        Cache::put($this->trackerKey($operationId), [
            'operation_id' => $operationId,
            'action' => $action,
            'status' => 'queued',
            'total' => $total,
            'processed' => 0,
            'ok' => 0,
            'failed' => 0,
            'requested_at' => $now->toIso8601String(),
            'started_at' => null,
            'updated_at' => $now->toIso8601String(),
            'completed_at' => null,
        ], self::TRACKER_TTL_SECONDS);

        BulkActionOperation::query()->create([
            'operation_id' => $operationId,
            'action' => $action,
            'status' => 'queued',
            'total' => $total,
            'processed' => 0,
            'ok' => 0,
            'failed' => 0,
            'actor_user_id' => $actorUserId,
            'requested_at' => $now,
        ]);
    }

    private function trackerKey(string $operationId): string
    {
        return 'bulk_action_status:'.$operationId;
    }

    private function toStatusPayload(BulkActionOperation $operation): array
    {
        return [
            'operation_id' => $operation->operation_id,
            'action' => $operation->action,
            'status' => $operation->status,
            'total' => $operation->total,
            'processed' => $operation->processed,
            'ok' => $operation->ok,
            'failed' => $operation->failed,
            'requested_at' => $operation->requested_at?->toIso8601String(),
            'started_at' => $operation->started_at?->toIso8601String(),
            'updated_at' => $operation->updated_at?->toIso8601String(),
            'completed_at' => $operation->completed_at?->toIso8601String(),
            'error' => $operation->error,
        ];
    }
}
