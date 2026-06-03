<?php

namespace App\Jobs;

use App\Models\Policy;
use App\Services\AutomationNotifier;
use App\Services\ScopedPolicyEvaluator;
use App\Services\StudentGraduationPolicyEvaluator;
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

    public function handle(
        ScopedPolicyEvaluator $scopedEvaluator,
        StudentGraduationPolicyEvaluator $graduationEvaluator,
        AutomationNotifier $notifier,
    ): void {
        Policy::query()->where('is_active', true)->get()->each(function (Policy $policy) use ($scopedEvaluator, $graduationEvaluator, $notifier): void {
            $policy->last_evaluated_at = now();

            if ($policy->execution_at && $policy->execution_at->isFuture()) {
                $policy->last_status = 'held';
                $policy->hold_reason = 'Execution time not reached.';
                $policy->save();
                $this->notifyHeld($policy, 'Execution time not reached.', $notifier);

                return;
            }

            $policyType = $this->resolvePolicyType($policy);

            if ($policyType === 'student_graduation') {
                $this->evaluateGraduationPolicy($policy, $graduationEvaluator, $notifier);

                return;
            }

            $this->evaluateScopedPolicy($policy, $scopedEvaluator, $notifier);
        });
    }

    private function evaluateGraduationPolicy(
        Policy $policy,
        StudentGraduationPolicyEvaluator $evaluator,
        AutomationNotifier $notifier,
    ): void {
        $result = $evaluator->evaluate($policy);
        $failures = $result['failures'];

        if ($result['warnings_sent'] === 0 && $result['deletion_warnings_sent'] === 0 && $result['suspended'] === 0 && $failures === []) {
            $policy->last_status = 'held';
            $policy->hold_reason = 'No graduated accounts due for warning, deletion warning, or suspension.';
            $policy->save();
            $this->notifyHeld($policy, 'No graduated accounts due for warning, deletion warning, or suspension.', $notifier);

            return;
        }

        $policy->last_status = $failures === [] ? 'executed' : 'held';
        $policy->hold_reason = $failures === []
            ? sprintf('Warnings sent: %d. Deletion warnings sent: %d. Suspended: %d.', $result['warnings_sent'], $result['deletion_warnings_sent'], $result['suspended'])
            : 'One or more graduation lifecycle operations failed.';
        $policy->save();

        if ($failures !== []) {
            $this->notifyFailures($policy, $failures, $notifier);
        }
    }

    private function evaluateScopedPolicy(
        Policy $policy,
        ScopedPolicyEvaluator $evaluator,
        AutomationNotifier $notifier,
    ): void {
        $result = $evaluator->evaluate($policy);
        $failures = $result['failures'];

        if ($result['processed'] === 0) {
            $policy->last_status = 'held';
            $policy->hold_reason = 'No accounts matched policy scope.';
            $policy->save();
            $this->notifyHeld($policy, 'No accounts matched policy scope.', $notifier);

            return;
        }

        $policy->last_status = $failures === [] ? 'executed' : 'held';
        $policy->hold_reason = $failures === [] ? null : 'One or more account operations failed.';
        $policy->save();

        if ($failures !== []) {
            $this->notifyFailures($policy, $failures, $notifier);
        }
    }

    private function resolvePolicyType(Policy $policy): string
    {
        $type = $policy->rule_json['type'] ?? 'scope';

        return is_string($type) ? $type : 'scope';
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
