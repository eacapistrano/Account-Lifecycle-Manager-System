<?php

namespace App\Services;

use App\Contracts\GoogleWorkspaceUserDeleter;
use App\Models\Student;
use RuntimeException;

class StudentAccountLifecycleService
{
    public function __construct(
        private GoogleWorkspaceUserDeleter $googleWorkspaceUserDeleter,
    ) {}

    public function suspendByExternalAccountId(string $externalAccountId): void
    {
        $updated = Student::query()
            ->where('external_account_id', $externalAccountId)
            ->update(['suspended' => true]);

        if ($updated === 0) {
            throw new RuntimeException('Student not found for external_account_id: '.$externalAccountId);
        }
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
