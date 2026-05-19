<?php

namespace App\Services;

use App\Mail\GraduationAccountNoticeMail;
use App\Models\Policy;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class StudentGraduationPolicyEvaluator
{
    public function __construct(
        private StudentAccountLifecycleService $lifecycle,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{warnings_sent: int, suspended: int, failures: array<int, array{external_account_id: string, error: string}>}
     */
    public function evaluate(Policy $policy): array
    {
        $rules = $policy->rule_json ?? [];
        $suspendAfterDays = max(1, (int) ($rules['suspend_after_days'] ?? config('automation.graduation.suspend_after_days', 60)));
        $warningDaysBefore = max(0, (int) ($rules['warning_days_before_suspend'] ?? config('automation.graduation.warning_days_before_suspend', 14)));
        $graduationStatus = trim((string) ($rules['graduation_status'] ?? 'graduated'));
        if ($graduationStatus === '') {
            $graduationStatus = 'graduated';
        }

        $today = now()->startOfDay();
        $warningsSent = 0;
        $suspended = 0;
        $failures = [];

        $students = Student::query()
            ->where('graduation_status', $graduationStatus)
            ->whereNotNull('graduation_date')
            ->where('suspended', false)
            ->get();

        foreach ($students as $student) {
            $graduationDate = Carbon::parse($student->graduation_date)->startOfDay();
            $suspendOn = $graduationDate->copy()->addDays($suspendAfterDays);
            $warningOn = $suspendOn->copy()->subDays($warningDaysBefore);

            if ($today->gte($suspendOn)) {
                $result = $this->suspendGraduate($policy, $student);
                if ($result === true) {
                    $suspended++;
                } elseif (is_string($result)) {
                    $failures[] = [
                        'external_account_id' => $student->external_account_id,
                        'error' => $result,
                    ];
                }

                continue;
            }

            if ($warningDaysBefore > 0 && $today->gte($warningOn) && $student->graduation_warning_sent_at === null) {
                $result = $this->sendWarning($policy, $student, $suspendOn, $today);
                if ($result === true) {
                    $warningsSent++;
                } elseif (is_string($result)) {
                    $failures[] = [
                        'external_account_id' => $student->external_account_id,
                        'error' => $result,
                    ];
                }
            }
        }

        return [
            'warnings_sent' => $warningsSent,
            'suspended' => $suspended,
            'failures' => $failures,
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array{eligible_warnings: int, eligible_suspensions: int}
     */
    public function previewCounts(array $rules = []): array
    {
        $status = trim((string) ($rules['graduation_status'] ?? 'graduated'));
        if ($status === '') {
            $status = 'graduated';
        }

        $suspendAfterDays = max(1, (int) ($rules['suspend_after_days'] ?? config('automation.graduation.suspend_after_days', 60)));
        $warningDaysBefore = max(0, (int) ($rules['warning_days_before_suspend'] ?? config('automation.graduation.warning_days_before_suspend', 14)));
        $today = now()->startOfDay();

        $eligibleWarnings = 0;
        $eligibleSuspensions = 0;

        Student::query()
            ->where('graduation_status', $status)
            ->whereNotNull('graduation_date')
            ->where('suspended', false)
            ->get()
            ->each(function (Student $student) use ($suspendAfterDays, $warningDaysBefore, $today, &$eligibleWarnings, &$eligibleSuspensions): void {
                $graduationDate = Carbon::parse($student->graduation_date)->startOfDay();
                $suspendOn = $graduationDate->copy()->addDays($suspendAfterDays);
                $warningOn = $suspendOn->copy()->subDays($warningDaysBefore);

                if ($today->gte($suspendOn)) {
                    $eligibleSuspensions++;

                    return;
                }

                if ($warningDaysBefore > 0 && $today->gte($warningOn) && $student->graduation_warning_sent_at === null) {
                    $eligibleWarnings++;
                }
            });

        return [
            'eligible_warnings' => $eligibleWarnings,
            'eligible_suspensions' => $eligibleSuspensions,
        ];
    }

    private function sendWarning(Policy $policy, Student $student, Carbon $suspendOn, Carbon $today): bool|string
    {
        try {
            Mail::to($student->primary_email)->send(new GraduationAccountNoticeMail(
                studentName: $student->full_name ?: $student->primary_email,
                suspendOn: $suspendOn,
                daysUntilSuspend: max(0, (int) $today->diffInDays($suspendOn, false)),
            ));

            $student->graduation_warning_sent_at = now();
            $student->save();

            $this->audit->record(
                'policy_execution',
                'policy.graduation_warning',
                $student->external_account_id,
                [
                    'policy_id' => $policy->id,
                    'suspend_on' => $suspendOn->toDateString(),
                ],
            );

            return true;
        } catch (\Throwable $e) {
            $this->audit->record(
                'policy_execution',
                'policy.graduation_warning',
                $student->external_account_id,
                ['policy_id' => $policy->id],
                false,
                $e->getMessage(),
            );

            return $e->getMessage();
        }
    }

    private function suspendGraduate(Policy $policy, Student $student): bool|string
    {
        try {
            $this->lifecycle->suspendByExternalAccountId($student->external_account_id);

            $this->audit->record(
                'policy_execution',
                'policy.suspend',
                $student->external_account_id,
                [
                    'policy_id' => $policy->id,
                    'reason' => 'graduation_suspend_after_days',
                    'graduation_date' => $student->graduation_date?->format('Y-m-d'),
                ],
            );

            return true;
        } catch (\Throwable $e) {
            $this->audit->record(
                'policy_execution',
                'policy.suspend',
                $student->external_account_id,
                ['policy_id' => $policy->id],
                false,
                $e->getMessage(),
            );

            return $e->getMessage();
        }
    }
}
