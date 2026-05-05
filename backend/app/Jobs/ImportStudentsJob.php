<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\StudentImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ImportStudentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public ?int $actorUserId = null,
        public ?string $correlationId = null,
    ) {}

    public function handle(StudentImportService $importer, AuditLogger $audit): void
    {
        $lock = Cache::lock('student_import_lock', (int) config('student_import.lock_ttl', 900));

        if (! $lock->get()) {
            return;
        }

        try {
            $stats = $importer->import();
            $actor = $this->actorUserId ? User::query()->find($this->actorUserId) : null;

            $audit->record(
                'student_deletion',
                'student.import',
                null,
                [
                    'correlation_id' => $this->correlationId,
                    'processed' => $stats['processed'],
                    'duration_ms' => $stats['duration_ms'],
                    'source' => 'database',
                ],
                true,
                null,
                $actor,
            );
        } catch (Throwable $e) {
            $actor = $this->actorUserId ? User::query()->find($this->actorUserId) : null;
            $audit->record(
                'student_deletion',
                'student.import',
                null,
                [
                    'correlation_id' => $this->correlationId,
                    'source' => 'database',
                ],
                false,
                $e->getMessage(),
                $actor,
            );

            throw $e;
        } finally {
            $lock->release();
        }
    }
}
