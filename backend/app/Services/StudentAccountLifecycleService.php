<?php

namespace App\Services;

use App\Contracts\GoogleWorkspaceUserDeleter;
use App\Contracts\GoogleWorkspaceUserSuspender;
use App\Models\Student;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class StudentAccountLifecycleService
{
    public function __construct(
        private GoogleWorkspaceUserDeleter $googleWorkspaceUserDeleter,
        private GoogleWorkspaceUserSuspender $googleWorkspaceUserSuspender,
    ) {}

    public function suspendByExternalAccountId(string $externalAccountId): void
    {
        $this->setSuspendedByExternalAccountId($externalAccountId, true);
    }

    public function unsuspendByExternalAccountId(string $externalAccountId): void
    {
        $this->setSuspendedByExternalAccountId($externalAccountId, false);
    }
public function unsuspendByPrimaryEmail(string $email): void
{
    $student = Student::where('primary_email', $email)
        ->firstOrFail();

    $this->googleWorkspaceUserSuspender->setSuspended($email, false);

    $student->suspended = false;
    $student->priority_flag = false;
    $student->compliance_notes = null;
    $student->deletion_scheduled_at = null;

    $student->save();
}
    private function setSuspendedByExternalAccountId(string $externalAccountId, bool $suspended): void
    {
        Log::info('StudentAccountLifecycleService.setSuspendedByExternalAccountId called', [
            'externalAccountId' => $externalAccountId,
            'suspended' => $suspended,
        ]);

        $student = Student::query()
            ->where('external_account_id', $externalAccountId)
            ->first();

        if ($student === null) {
            Log::error('Student not found', ['externalAccountId' => $externalAccountId]);
            throw new RuntimeException('Student not found for external_account_id: '.$externalAccountId);
        }

        Log::info('Found student', [
            'externalAccountId' => $externalAccountId,
            'email' => $student->primary_email,
            'currentSuspendedStatus' => $student->suspended,
        ]);

        $student->suspended = $suspended;
        $student->save();

        Log::info('Updated local student record', [
            'externalAccountId' => $externalAccountId,
            'email' => $student->primary_email,
            'newSuspendedStatus' => $suspended,
        ]);

        if (! config('google_workspace.suspend_enabled')) {
            Log::info('Google Workspace suspension is disabled, skipping API call', [
                'externalAccountId' => $externalAccountId,
            ]);
            return;
        }

        if (config('google_workspace.suspend_dry_run')) {
            Log::info('Google Workspace dry-run mode enabled, skipping actual API call', [
                'externalAccountId' => $externalAccountId,
                'email' => $student->primary_email,
                'wouldsuspend' => $suspended,
            ]);
            return;
        }

        $userKey = $this->googleWorkspaceUserKey($student);
        Log::info('Calling Google Workspace suspend API', [
            'externalAccountId' => $externalAccountId,
            'email' => $student->primary_email,
            'userKey' => $userKey,
            'suspended' => $suspended,
        ]);

        try {
            $this->googleWorkspaceUserSuspender->setSuspended($userKey, $suspended);
            Log::info('Successfully called Google Workspace suspend API', [
                'externalAccountId' => $externalAccountId,
                'userKey' => $userKey,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to call Google Workspace suspend API', [
                'externalAccountId' => $externalAccountId,
                'userKey' => $userKey,
                'errorMessage' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function googleWorkspaceUserKey(Student $student): string
    {
        if (config('google_workspace.suspend_user_key') === 'primary_email') {
            return $student->primary_email;
        }

        return $student->primary_email; // Default to primary_email if config is invalid or not set, since external_account_id may not be unique for suspension.
    }

    /**
     * Deletes a student matched by lookup string: resolves `external_account_id` first, then `primary_email`.
     *
     * @param  string  $bulkAccountIdentifier  Value from bulk `account_ids` / `google_ids`.
     */
    public function deleteByExternalAccountId(string $bulkAccountIdentifier): void
    {
        $student = $this->findStudentForBulkAction($bulkAccountIdentifier);

        if ($student === null) {
            throw new RuntimeException(
                'Student not found for external_account_id or primary_email: '.$bulkAccountIdentifier
            );
        }

        if (config('security.student_delete_dry_run')) {
            return;
        }

        $userKey = $this->googleUserKeyForDeletion($student);
        $this->googleWorkspaceUserDeleter->deleteUser($userKey);

        $student->delete();
    }

    private function googleUserKeyForDeletion(Student $student): string
    {
        if (config('google_workspace.delete_user_key') === 'primary_email') {
            return $student->primary_email;
        }

        return $student->external_account_id;
    }

    private function findStudentForBulkAction(string $bulkAccountIdentifier): ?Student
    {
        $byExternalId = Student::query()
            ->where('external_account_id', $bulkAccountIdentifier)
            ->first();

        if ($byExternalId !== null) {
            return $byExternalId;
        }

        return Student::query()
            ->where('primary_email', $bulkAccountIdentifier)
            ->first();
    }
}
