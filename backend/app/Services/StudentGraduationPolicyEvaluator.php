<?php

namespace App\Services;

use App\Mail\GraduationAccountDeletionNoticeMail;
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
     * @return array{warnings_sent: int, deletion_warnings_sent: int, suspended: int, failures: array<int, array{external_account_id: string, error: string}>}
     */
    public function evaluate(Policy $policy): array
    {
        $rules = $policy->rule_json ?? [];
        $suspendAfterDays = max(1, (int) ($rules['suspend_after_days'] ?? config('automation.graduation.suspend_after_days', 60)));
        $warningDaysBefore = max(0, (int) ($rules['warning_days_before_suspend'] ?? config('automation.graduation.warning_days_before_suspend', 14)));
        $deleteAfterDays = max(0, (int) ($rules['permanent_delete_after_days'] ?? 0));
        $warningDaysBeforeDelete = max(0, (int) ($rules['warning_days_before_delete'] ?? 0));
        $graduationStatus = trim((string) ($rules['graduation_status'] ?? 'graduated'));
        if ($graduationStatus === '') {
            $graduationStatus = 'graduated';
        }

        $today = now()->startOfDay();
        $warningsSent = 0;
        $deletionWarningsSent = 0;
        $suspended = 0;
        $failures = [];

        $students = Student::query()
            ->where('graduation_status', $graduationStatus)
            ->whereNotNull('graduation_date')
            ->get();

        foreach ($students as $student) {
            $graduationDate = Carbon::parse($student->graduation_date)->startOfDay();
            $suspendOn = $graduationDate->copy()->addDays($suspendAfterDays);
            $warningOn = $suspendOn->copy()->subDays($warningDaysBefore);
            $deleteOn = $deleteAfterDays > 0 ? $suspendOn->copy()->addDays($deleteAfterDays) : null;

            if ($today->gte($suspendOn) && ! $student->suspended) {
                $result = $this->suspendGraduate($policy, $student);
                if ($result === true) {
                    $suspended++;
                    if ($deleteOn !== null && $student->deletion_scheduled_at === null) {
                        $student->deletion_scheduled_at = $deleteOn;
                        $student->save();
                    }
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

            if ($deleteAfterDays > 0 && $student->suspended) {
                $scheduledDeletion = $student->deletion_scheduled_at ? Carbon::parse($student->deletion_scheduled_at)->startOfDay() : $deleteOn;
                if ($scheduledDeletion !== null && $student->deletion_scheduled_at === null) {
                    $student->deletion_scheduled_at = $scheduledDeletion;
                    $student->save();
                }

                if ($warningDaysBeforeDelete > 0 && $scheduledDeletion !== null) {
                    $deletionWarningOn = $scheduledDeletion->copy()->subDays($warningDaysBeforeDelete);
                    if ($today->gte($deletionWarningOn) && $student->graduation_deletion_warning_sent_at === null) {
                        $result = $this->sendDeletionWarning($policy, $student, $scheduledDeletion, $today);
                        if ($result === true) {
                            $deletionWarningsSent++;
                        } elseif (is_string($result)) {
                            $failures[] = [
                                'external_account_id' => $student->external_account_id,
                                'error' => $result,
                            ];
                        }
                    }
                }
            }
        }

        return [
            'warnings_sent' => $warningsSent,
            'deletion_warnings_sent' => $deletionWarningsSent,
            'suspended' => $suspended,
            'failures' => $failures,
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array{eligible_warnings: int, eligible_suspensions: int, eligible_deletion_warnings: int}
     */
    public function previewCounts(array $rules = []): array
    {
        $status = trim((string) ($rules['graduation_status'] ?? 'graduated'));
        if ($status === '') {
            $status = 'graduated';
        }

        $suspendAfterDays = max(1, (int) ($rules['suspend_after_days'] ?? config('automation.graduation.suspend_after_days', 60)));
        $warningDaysBefore = max(0, (int) ($rules['warning_days_before_suspend'] ?? config('automation.graduation.warning_days_before_suspend', 14)));
        $deleteAfterDays = max(0, (int) ($rules['permanent_delete_after_days'] ?? 0));
        $warningDaysBeforeDelete = max(0, (int) ($rules['warning_days_before_delete'] ?? 0));
        $today = now()->startOfDay();

        $eligibleWarnings = 0;
        $eligibleSuspensions = 0;
        $eligibleDeletionWarnings = 0;

        Student::query()
            ->where('graduation_status', $status)
            ->whereNotNull('graduation_date')
            ->get()
            ->each(function (Student $student) use ($suspendAfterDays, $warningDaysBefore, $deleteAfterDays, $warningDaysBeforeDelete, $today, &$eligibleWarnings, &$eligibleSuspensions, &$eligibleDeletionWarnings): void {
                $graduationDate = Carbon::parse($student->graduation_date)->startOfDay();
                $suspendOn = $graduationDate->copy()->addDays($suspendAfterDays);
                $warningOn = $suspendOn->copy()->subDays($warningDaysBefore);

                if (! $student->suspended && $today->gte($suspendOn)) {
                    $eligibleSuspensions++;

                    return;
                }

                if ($warningDaysBefore > 0 && ! $student->suspended && $today->gte($warningOn) && $student->graduation_warning_sent_at === null) {
                    $eligibleWarnings++;
                }

                if ($deleteAfterDays > 0 && $student->suspended) {
                    $scheduledDeletion = $student->deletion_scheduled_at ? Carbon::parse($student->deletion_scheduled_at)->startOfDay() : $suspendOn->copy()->addDays($deleteAfterDays);
                    if ($warningDaysBeforeDelete > 0 && $student->graduation_deletion_warning_sent_at === null) {
                        $deletionWarningOn = $scheduledDeletion->copy()->subDays($warningDaysBeforeDelete);
                        if ($today->gte($deletionWarningOn)) {
                            $eligibleDeletionWarnings++;
                        }
                    }
                }
            });

        return [
            'eligible_warnings' => $eligibleWarnings,
            'eligible_suspensions' => $eligibleSuspensions,
            'eligible_deletion_warnings' => $eligibleDeletionWarnings,
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

            $student->graduation_warning_sent_at = \Illuminate\Support\Carbon::now();
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

    private function sendDeletionWarning(Policy $policy, Student $student, Carbon $deleteOn, Carbon $today): bool|string
    {
        try {
            Mail::to($student->primary_email)->send(new GraduationAccountDeletionNoticeMail(
                studentName: $student->full_name ?: $student->primary_email,
                deleteOn: $deleteOn,
                daysUntilDelete: max(0, (int) $today->diffInDays($deleteOn, false)),
            ));

            $student->graduation_deletion_warning_sent_at = \Illuminate\Support\Carbon::now();
            $student->save();

            $this->audit->record(
                'policy_execution',
                'policy.graduation_deletion_warning',
                $student->external_account_id,
                [
                    'policy_id' => $policy->id,
                    'delete_on' => $deleteOn->toDateString(),
                ],
            );

            return true;
        } catch (\Throwable $e) {
            $this->audit->record(
                'policy_execution',
                'policy.graduation_deletion_warning',
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
                    'graduation_date' => $student->graduation_date ? Carbon::parse($student->graduation_date)->toDateString() : null,
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
