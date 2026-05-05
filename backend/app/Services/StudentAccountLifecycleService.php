<?php

namespace App\Services;

use App\Models\Student;
use RuntimeException;

class StudentAccountLifecycleService
{
    public function suspendByExternalAccountId(string $externalAccountId): void
    {
        $updated = Student::query()
            ->where('external_account_id', $externalAccountId)
            ->update(['suspended' => true]);

        if ($updated === 0) {
            throw new RuntimeException('Student not found for external_account_id: '.$externalAccountId);
        }
    }

    public function deleteByExternalAccountId(string $externalAccountId): void
    {
        $deleted = Student::query()
            ->where('external_account_id', $externalAccountId)
            ->delete();

        if ($deleted === 0) {
            throw new RuntimeException('Student not found for external_account_id: '.$externalAccountId);
        }
    }
}
