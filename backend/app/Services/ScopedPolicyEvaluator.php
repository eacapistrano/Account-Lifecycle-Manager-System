<?php

namespace App\Services;

use App\Models\Policy;
use App\Models\Student;
use Illuminate\Support\Collection;

class ScopedPolicyEvaluator
{
    public function __construct(
        private StudentAccountLifecycleService $lifecycle,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{processed: int, failures: array<int, array{external_account_id: string, error: string}>}
     */
    public function evaluate(Policy $policy): array
    {
        $rules = $policy->rule_json ?? [];
        $q = Student::query();
        if (! empty($rules['department'])) {
            $q->where('department', $rules['department']);
        }
        if (! empty($rules['school_year'])) {
            $q->where('school_year', $rules['school_year']);
        }

        /** @var Collection<int, Student> $students */
        $students = $q->get();
        $failures = [];

        foreach ($students as $student) {
            $externalId = $student->external_account_id;
            try {
                if ($policy->action === 'suspend') {
                    $this->lifecycle->suspendByExternalAccountId($externalId);
                } else {
                    $this->lifecycle->deleteByExternalAccountId($externalId);
                }

                $this->audit->record(
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
                $this->audit->record(
                    'policy_execution',
                    'policy.'.$policy->action,
                    $externalId,
                    ['policy_id' => $policy->id],
                    false,
                    $e->getMessage(),
                );
            }
        }

        return [
            'processed' => $students->count(),
            'failures' => $failures,
        ];
    }
}
