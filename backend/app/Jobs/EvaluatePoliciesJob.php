<?php

namespace App\Jobs;

use App\Models\Policy;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\AutomationNotifier;
use App\Services\StudentAccountLifecycleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EvaluatePoliciesJob implements ShouldQueue
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
        Policy::query()->where('is_active', true)->get()->each(function (Policy $policy) use ($lifecycle, $audit, $notifier): void {
            $policy->last_evaluated_at = now();

            if ($policy->execution_at && $policy->execution_at->isFuture()) {
                $policy->last_status = 'held';
                $policy->hold_reason = 'Execution time not reached.';
                $policy->save();
                $this->notifyHeld($policy, 'Execution time not reached.', $notifier);

                return;
            }

            $rules = $policy->rule_json ?? [];
            $q = Student::query();
            if (! empty($rules['department'])) {
                $q->where('department', $rules['department']);
            }
            if (! empty($rules['school_year'])) {
                $q->where('school_year', $rules['school_year']);
            }

            $students = $q->get();

            if ($students->isEmpty()) {
                $policy->last_status = 'held';
                $policy->hold_reason = 'No accounts matched policy scope.';
                $policy->save();
                $this->notifyHeld($policy, 'No accounts matched policy scope.', $notifier);

                return;
            }

            $failures = [];
            foreach ($students as $student) {
                $externalId = $student->external_account_id;
                try {
                    if ($policy->action === 'suspend') {
                        $lifecycle->suspendByExternalAccountId($externalId);
                    } else {
                        $lifecycle->deleteByExternalAccountId($externalId);
                    }

                    $audit->record(
                        'policy_execution',
                        'policy.'.$policy->action,
                        $externalId,
                        ['policy_id' => $policy->id],
                    );
                } catch (\Throwable $e) {
                    $failures[] = [
                        'external_account_id' => $externalId,
                        'error' => $e->getMessage(),
                    ];
                    $audit->record(
                        'policy_execution',
                        'policy.'.$policy->action,
                        $externalId,
                        ['policy_id' => $policy->id],
                        false,
                        $e->getMessage(),
                    );
                }
            }

            $policy->last_status = $failures === [] ? 'executed' : 'held';
            $policy->hold_reason = $failures === [] ? null : 'One or more account operations failed.';
            $policy->save();

            if ($failures !== []) {
                $this->notifyFailures($policy, $failures, $notifier);
            }
        });
    }

    protected function notifyHeld(Policy $policy, string $reason, AutomationNotifier $notifier): void
    {
        $notifier->send(
            sprintf('Policy %d held: %s', $policy->id, $policy->name),
            implode("\n", [
                'Policy evaluation result: HELD',
                sprintf('Policy ID: %d', $policy->id),
                sprintf('Policy Name: %s', $policy->name),
                sprintf('Action: %s', $policy->action),
                sprintf('Reason: %s', $reason),
                sprintf('Evaluated At: %s', now()->toIso8601String()),
            ])
        );
    }

    /**
     * @param  array<int, array{external_account_id: string, error: string}>  $failures
     */
    protected function notifyFailures(Policy $policy, array $failures, AutomationNotifier $notifier): void
    {
        $lines = [
            'Policy evaluation completed with failures.',
            sprintf('Policy ID: %d', $policy->id),
            sprintf('Policy Name: %s', $policy->name),
            sprintf('Action: %s', $policy->action),
            sprintf('Failed Accounts: %d', count($failures)),
            '',
            'Failure details:',
        ];

        foreach ($failures as $failure) {
            $lines[] = sprintf('- %s: %s', $failure['external_account_id'], $failure['error']);
        }

        $notifier->send(
            sprintf('Policy %d execution failures', $policy->id),
            implode("\n", $lines)
        );
    }
}
