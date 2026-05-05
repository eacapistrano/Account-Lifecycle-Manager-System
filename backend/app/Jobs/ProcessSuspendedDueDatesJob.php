<?php

namespace App\Jobs;

use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\AutomationNotifier;
use App\Services\StudentAccountLifecycleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSuspendedDueDatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(StudentAccountLifecycleService $lifecycle, AuditLogger $audit, AutomationNotifier $notifier): void
    {
        $failures = [];
        $processed = 0;

        Student::query()
            ->where('suspended', true)
            ->whereNotNull('deletion_scheduled_at')
            ->where('deletion_scheduled_at', '<=', now())
            ->each(function (Student $student) use ($lifecycle, $audit, &$failures, &$processed): void {
                $externalId = $student->external_account_id;
                $studentId = $student->id;
                try {
                    $lifecycle->deleteByExternalAccountId($externalId);
                    $audit->record(
                        'suspended_accounts',
                        'auto_delete',
                        $externalId,
                        ['student_id' => $studentId],
                    );
                    $processed++;
                } catch (\Throwable $e) {
                    $failures[] = [
                        'student_id' => $student->id,
                        'external_account_id' => $externalId,
                        'error' => $e->getMessage(),
                    ];
                    $audit->record(
                        'suspended_accounts',
                        'auto_delete',
                        $externalId,
                        ['student_id' => $student->id],
                        false,
                        $e->getMessage(),
                    );
                }
            });

        if ($failures !== []) {
            $lines = [
                'Suspended due-date sweep completed with failures.',
                sprintf('Successful deletions: %d', $processed),
                sprintf('Failed deletions: %d', count($failures)),
                sprintf('Evaluated At: %s', now()->toIso8601String()),
                '',
                'Failure details:',
            ];

            foreach ($failures as $failure) {
                $lines[] = sprintf(
                    '- student_id=%d external_account_id=%s error=%s',
                    $failure['student_id'],
                    $failure['external_account_id'],
                    $failure['error']
                );
            }

            $notifier->send(
                'Suspended due-date sweep failures',
                implode("\n", $lines)
            );
        }
    }
}
