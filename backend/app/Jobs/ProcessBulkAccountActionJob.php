<?php

namespace App\Jobs;

use App\Models\BulkActionOperation;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StudentAccountLifecycleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProcessBulkAccountActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $accountIds
     */
    public function __construct(
        public string $action,
        public array $accountIds,
        public string $operationId,
        public ?int $actorUserId = null,
        public ?string $actorIp = null,
        public ?string $correlationId = null,
    ) {}

    public function handle(StudentAccountLifecycleService $lifecycle, AuditLogger $audit): void
    {
        $actor = $this->actorUserId ? User::query()->find($this->actorUserId) : null;
        $correlationId = $this->correlationId ?: (string) Str::uuid();
        $trackerKey = $this->trackerKey();

        $this->updateTrackerStatus('running');
        $this->syncPersistentStatus([
            'started_at' => now(),
        ]);

        foreach ($this->accountIds as $externalAccountId) {
            $successful = false;
            $studentSnapshot = $this->studentSnapshotForBulkIdentifier($externalAccountId);
            try {
                if ($this->action === 'suspend') {
                    $lifecycle->suspendByExternalAccountId($externalAccountId);
                } elseif ($this->action === 'delete') {
                    $lifecycle->deleteByExternalAccountId($externalAccountId);
                } else {
                    throw new \InvalidArgumentException('Unsupported bulk account action: '.$this->action);
                }

                $audit->record(
                    'student_deletion',
                    'student.'.$this->action,
                    $externalAccountId,
                    [
                        'external_account_id' => $externalAccountId,
                        ...$studentSnapshot,
                        'operation_id' => $this->operationId,
                        'queued' => true,
                        'job_class' => self::class,
                    ],
                    true,
                    null,
                    $actor,
                );
                $successful = true;
            } catch (\Throwable $e) {
                $audit->record(
                    'student_deletion',
                    'student.'.$this->action,
                    $externalAccountId,
                    [
                        'external_account_id' => $externalAccountId,
                        ...$studentSnapshot,
                        'operation_id' => $this->operationId,
                        'queued' => true,
                        'job_class' => self::class,
                        'error_context' => [
                            'correlation_id' => $correlationId,
                            'actor_ip' => $this->actorIp,
                        ],
                    ],
                    false,
                    $e->getMessage(),
                    $actor,
                );
            } finally {
                $status = Cache::get($trackerKey);
                if (is_array($status)) {
                    $nextProcessed = (int) ($status['processed'] ?? 0) + 1;
                    $nextOk = (int) ($status['ok'] ?? 0) + ($successful ? 1 : 0);
                    $nextFailed = (int) ($status['failed'] ?? 0) + ($successful ? 0 : 1);
                    $total = (int) ($status['total'] ?? count($this->accountIds));
                    $isDone = $nextProcessed >= $total;
                    Cache::put($trackerKey, [
                        ...$status,
                        'status' => $isDone ? 'completed' : 'running',
                        'processed' => $nextProcessed,
                        'ok' => $nextOk,
                        'failed' => $nextFailed,
                        'updated_at' => now()->toIso8601String(),
                        'completed_at' => $isDone ? now()->toIso8601String() : null,
                    ], 86400);

                    $this->syncPersistentStatus([
                        'status' => $isDone ? 'completed' : 'running',
                        'processed' => $nextProcessed,
                        'ok' => $nextOk,
                        'failed' => $nextFailed,
                        'completed_at' => $isDone ? now() : null,
                    ]);
                }
            }
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $status = Cache::get($this->trackerKey());
        if (! is_array($status)) {
            return;
        }

        Cache::put($this->trackerKey(), [
            ...$status,
            'status' => 'failed',
            'updated_at' => now()->toIso8601String(),
            'completed_at' => now()->toIso8601String(),
            'error' => $exception?->getMessage(),
        ], 86400);

        $this->syncPersistentStatus([
            'status' => 'failed',
            'completed_at' => now(),
            'error' => $exception?->getMessage(),
        ]);
    }

    private function updateTrackerStatus(string $nextStatus, array $extra = []): void
    {
        $status = Cache::get($this->trackerKey());
        if (! is_array($status)) {
            $this->syncPersistentStatus([
                'status' => $nextStatus,
                ...$extra,
            ]);

            return;
        }

        Cache::put($this->trackerKey(), [
            ...$status,
            'status' => $nextStatus,
            'updated_at' => now()->toIso8601String(),
            ...$extra,
        ], 86400);

        $this->syncPersistentStatus([
            'status' => $nextStatus,
            ...$extra,
        ]);
    }

    private function trackerKey(): string
    {
        return 'bulk_action_status:'.$this->operationId;
    }

    private function syncPersistentStatus(array $changes): void
    {
        BulkActionOperation::query()
            ->where('operation_id', $this->operationId)
            ->update($changes);
    }

    /**
     * @return array{primary_email?: string, student_id?: int, resolved_external_account_id?: string}
     */
    private function studentSnapshotForBulkIdentifier(string $bulkAccountIdentifier): array
    {
        $student = Student::query()
            ->where('external_account_id', $bulkAccountIdentifier)
            ->orWhere('primary_email', $bulkAccountIdentifier)
            ->first();

        if ($student === null) {
            return [];
        }

        return [
            'primary_email' => $student->primary_email,
            'student_id' => $student->id,
            'resolved_external_account_id' => $student->external_account_id,
        ];
    }
}
