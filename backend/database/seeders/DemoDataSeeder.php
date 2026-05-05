<?php

namespace Database\Seeders;

use App\Models\AuditEvent;
use App\Models\BulkActionOperation;
use App\Models\Policy;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    private const EXTRA_FACTORY_STUDENTS = 24;

    public function run(): void
    {
        $adminRoleId = (int) Role::query()->where('slug', 'admin')->value('id');
        $viewerRoleId = (int) Role::query()->where('slug', 'viewer')->value('id');

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'IT Admin',
                'password' => Hash::make('password'),
                'role_id' => $adminRoleId !== 0 ? $adminRoleId : 1,
            ],
        );

        $operator = User::query()->updateOrCreate(
            ['email' => 'operator@example.com'],
            [
                'name' => 'Directory Operator',
                'password' => Hash::make('password'),
                'role_id' => $viewerRoleId !== 0 ? $viewerRoleId : 2,
            ],
        );

        AuditEvent::query()->delete();
        BulkActionOperation::query()->delete();
        Policy::query()->delete();
        Student::query()->delete();

        $synced = now()->subHours(2);

        Student::query()->insert([
            [
                'external_account_id' => 'demo_1001',
                'primary_email' => 'ava.chen@school.example',
                'full_name' => 'Ava Chen',
                'department' => 'Science',
                'school_year' => '2026',
                'graduation_date' => '2027-05-20',
                'graduation_status' => 'enrolled',
                'degree_program' => 'BSc Natural Sciences',
                'suspended' => false,
                'deletion_scheduled_at' => null,
                'priority_flag' => false,
                'compliance_notes' => null,
                'raw_json' => json_encode(['kind' => 'admin#user', 'id' => 'demo_1001']),
                'last_imported_at' => $synced,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'external_account_id' => 'demo_1002',
                'primary_email' => 'noah.patel@school.example',
                'full_name' => 'Noah Patel',
                'department' => 'Science',
                'school_year' => '2026',
                'graduation_date' => '2027-05-20',
                'graduation_status' => 'enrolled',
                'degree_program' => 'BSc Physics',
                'suspended' => false,
                'deletion_scheduled_at' => null,
                'priority_flag' => false,
                'compliance_notes' => null,
                'raw_json' => json_encode(['kind' => 'admin#user', 'id' => 'demo_1002']),
                'last_imported_at' => $synced,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'external_account_id' => 'demo_1003',
                'primary_email' => 'mia.rossi@school.example',
                'full_name' => 'Mia Rossi',
                'department' => 'Arts',
                'school_year' => '2025',
                'graduation_date' => '2025-11-10',
                'graduation_status' => 'withdrawn',
                'degree_program' => 'BA Studio Art',
                'suspended' => true,
                'deletion_scheduled_at' => null,
                'priority_flag' => false,
                'compliance_notes' => 'Awaiting guardian response.',
                'raw_json' => json_encode(['kind' => 'admin#user', 'id' => 'demo_1003']),
                'last_imported_at' => $synced,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'external_account_id' => 'demo_1004',
                'primary_email' => 'liam.kim@school.example',
                'full_name' => 'Liam Kim',
                'department' => 'Arts',
                'school_year' => '2025',
                'graduation_date' => '2025-06-28',
                'graduation_status' => 'graduated',
                'degree_program' => 'BA Visual Communication',
                'suspended' => true,
                'deletion_scheduled_at' => now()->subDays(3),
                'priority_flag' => true,
                'compliance_notes' => 'Due for archival — priority queue.',
                'raw_json' => json_encode(['kind' => 'admin#user', 'id' => 'demo_1004']),
                'last_imported_at' => $synced,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'external_account_id' => 'demo_1005',
                'primary_email' => 'emma.nguyen@school.example',
                'full_name' => 'Emma Nguyen',
                'department' => 'Sports',
                'school_year' => '2024',
                'graduation_date' => '2024-09-01',
                'graduation_status' => 'graduated',
                'degree_program' => 'BPE Sport and Exercise Science',
                'suspended' => true,
                'deletion_scheduled_at' => now()->addWeek(),
                'priority_flag' => false,
                'compliance_notes' => null,
                'raw_json' => json_encode(['kind' => 'admin#user', 'id' => 'demo_1005']),
                'last_imported_at' => $synced,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'external_account_id' => 'demo_1006',
                'primary_email' => 'james.omalley@school.example',
                'full_name' => 'James O\'Malley',
                'department' => 'Sports',
                'school_year' => '2024',
                'graduation_date' => '2026-12-15',
                'graduation_status' => 'enrolled',
                'degree_program' => 'BS Kinesiology',
                'suspended' => false,
                'deletion_scheduled_at' => null,
                'priority_flag' => false,
                'compliance_notes' => null,
                'raw_json' => json_encode(['kind' => 'admin#user', 'id' => 'demo_1006']),
                'last_imported_at' => $synced,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        if (self::EXTRA_FACTORY_STUDENTS > 0) {
            Student::factory()
                ->count(self::EXTRA_FACTORY_STUDENTS)
                ->create();
        }

        Policy::query()->insert([
            [
                'name' => 'Suspend graduated — Arts 2025',
                'action' => 'suspend',
                'rule_json' => json_encode(['department' => 'Arts', 'school_year' => '2025']),
                'execution_at' => now()->subDay(),
                'cron_expression' => '0 6 * * *',
                'is_active' => true,
                'last_evaluated_at' => now()->subHours(6),
                'last_status' => 'executed',
                'hold_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Delete leavers — Sports 2024 (scheduled)',
                'action' => 'delete',
                'rule_json' => json_encode(['department' => 'Sports', 'school_year' => '2024']),
                'execution_at' => now()->addDays(7),
                'cron_expression' => null,
                'is_active' => true,
                'last_evaluated_at' => now()->subHours(1),
                'last_status' => 'held',
                'hold_reason' => 'Execution time not reached.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Science cohort nightly review',
                'action' => 'suspend',
                'rule_json' => json_encode(['department' => 'Science']),
                'execution_at' => null,
                'cron_expression' => '15 2 * * *',
                'is_active' => true,
                'last_evaluated_at' => null,
                'last_status' => 'idle',
                'hold_reason' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Retired: empty OU catch-all',
                'action' => 'delete',
                'rule_json' => json_encode(['department' => 'NonexistentDept']),
                'execution_at' => null,
                'cron_expression' => null,
                'is_active' => false,
                'last_evaluated_at' => now()->subMonth(),
                'last_status' => 'held',
                'hold_reason' => 'No accounts matched policy scope.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $policySuspendArtsId = Policy::query()->where('name', 'Suspend graduated — Arts 2025')->value('id');
        $policyDeleteSportsId = Policy::query()->where('name', 'Delete leavers — Sports 2024 (scheduled)')->value('id');

        $opCompletedSuspend = (string) Str::uuid();
        $opCompletedDelete = (string) Str::uuid();
        $opRunning = (string) Str::uuid();
        $opQueued = (string) Str::uuid();
        $opFailed = (string) Str::uuid();
        $opWithFailures = (string) Str::uuid();

        $reqPast = now()->subDays(2);
        $startedPast = $reqPast->copy()->addMinutes(1);
        $completedPast = $startedPast->copy()->addMinutes(5);

        BulkActionOperation::query()->insert([
            [
                'operation_id' => $opCompletedSuspend,
                'action' => 'suspend',
                'status' => 'completed',
                'total' => 3,
                'processed' => 3,
                'ok' => 3,
                'failed' => 0,
                'actor_user_id' => $admin->id,
                'requested_at' => $reqPast,
                'started_at' => $startedPast,
                'completed_at' => $completedPast,
                'error' => null,
                'created_at' => $reqPast,
                'updated_at' => $completedPast,
            ],
            [
                'operation_id' => $opCompletedDelete,
                'action' => 'delete',
                'status' => 'completed',
                'total' => 1,
                'processed' => 1,
                'ok' => 1,
                'failed' => 0,
                'actor_user_id' => $admin->id,
                'requested_at' => now()->subDay(),
                'started_at' => now()->subDay()->addMinutes(2),
                'completed_at' => now()->subDay()->addMinutes(8),
                'error' => null,
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay()->addMinutes(8),
            ],
            [
                'operation_id' => $opRunning,
                'action' => 'suspend',
                'status' => 'running',
                'total' => 50,
                'processed' => 12,
                'ok' => 11,
                'failed' => 1,
                'actor_user_id' => $operator->id,
                'requested_at' => now()->subMinutes(20),
                'started_at' => now()->subMinutes(19),
                'completed_at' => null,
                'error' => null,
                'created_at' => now()->subMinutes(20),
                'updated_at' => now()->subMinutes(1),
            ],
            [
                'operation_id' => $opQueued,
                'action' => 'delete',
                'status' => 'queued',
                'total' => 8,
                'processed' => 0,
                'ok' => 0,
                'failed' => 0,
                'actor_user_id' => $operator->id,
                'requested_at' => now()->subMinutes(3),
                'started_at' => null,
                'completed_at' => null,
                'error' => null,
                'created_at' => now()->subMinutes(3),
                'updated_at' => now()->subMinutes(3),
            ],
            [
                'operation_id' => $opFailed,
                'action' => 'suspend',
                'status' => 'failed',
                'total' => 4,
                'processed' => 0,
                'ok' => 0,
                'failed' => 0,
                'actor_user_id' => $admin->id,
                'requested_at' => now()->subDays(5),
                'started_at' => now()->subDays(5),
                'completed_at' => now()->subDays(5),
                'error' => 'Queue worker lost connection to Redis.',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ],
            [
                'operation_id' => $opWithFailures,
                'action' => 'suspend',
                'status' => 'completed',
                'total' => 2,
                'processed' => 2,
                'ok' => 1,
                'failed' => 1,
                'actor_user_id' => $operator->id,
                'requested_at' => now()->subHours(4),
                'started_at' => now()->subHours(4)->addMinute(),
                'completed_at' => now()->subHours(4)->addMinutes(6),
                'error' => null,
                'created_at' => now()->subHours(4),
                'updated_at' => now()->subHours(4)->addMinutes(6),
            ],
        ]);

        $corr = static fn (): string => (string) Str::uuid();

        AuditEvent::query()->insert([
            [
                'actor_user_id' => $admin->id,
                'module' => 'student_deletion',
                'action' => 'directory.sync',
                'target_account_id' => null,
                'payload' => json_encode(['imported' => 6, 'mock' => true]),
                'correlation_id' => $corr(),
                'ip_address' => '127.0.0.1',
                'success' => true,
                'error_message' => null,
                'created_at' => now()->subHours(3),
                'updated_at' => now()->subHours(3),
            ],
            [
                'actor_user_id' => $admin->id,
                'module' => 'student_deletion',
                'action' => 'student.suspend',
                'target_account_id' => 'demo_1001',
                'payload' => json_encode([
                    'external_account_id' => 'demo_1001',
                    'operation_id' => $opCompletedSuspend,
                    'queued' => true,
                ]),
                'correlation_id' => $corr(),
                'ip_address' => '203.0.113.10',
                'success' => true,
                'error_message' => null,
                'created_at' => $startedPast,
                'updated_at' => $startedPast,
            ],
            [
                'actor_user_id' => $admin->id,
                'module' => 'student_deletion',
                'action' => 'student.delete',
                'target_account_id' => 'demo_legacy_09',
                'payload' => json_encode([
                    'external_account_id' => 'demo_legacy_09',
                    'operation_id' => $opCompletedDelete,
                    'queued' => true,
                ]),
                'correlation_id' => $corr(),
                'ip_address' => '203.0.113.10',
                'success' => true,
                'error_message' => null,
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay(),
            ],
            [
                'actor_user_id' => $operator->id,
                'module' => 'policy_execution',
                'action' => 'policy.suspend',
                'target_account_id' => 'demo_1003',
                'payload' => json_encode(['policy_id' => $policySuspendArtsId]),
                'correlation_id' => $corr(),
                'ip_address' => null,
                'success' => true,
                'error_message' => null,
                'created_at' => now()->subHours(6),
                'updated_at' => now()->subHours(6),
            ],
            [
                'actor_user_id' => $operator->id,
                'module' => 'policy_execution',
                'action' => 'policy.delete',
                'target_account_id' => 'demo_old_account',
                'payload' => json_encode(['policy_id' => $policyDeleteSportsId]),
                'correlation_id' => $corr(),
                'ip_address' => null,
                'success' => false,
                'error_message' => 'Resource not found: user demo_old_account',
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(2),
            ],
            [
                'actor_user_id' => $operator->id,
                'module' => 'student_deletion',
                'action' => 'student.suspend',
                'target_account_id' => 'demo_unknown_77',
                'payload' => json_encode([
                    'external_account_id' => 'demo_unknown_77',
                    'operation_id' => $opWithFailures,
                    'queued' => true,
                    'error_context' => ['correlation_id' => $corr()],
                ]),
                'correlation_id' => $corr(),
                'ip_address' => '198.51.100.5',
                'success' => false,
                'error_message' => 'User not found in directory.',
                'created_at' => now()->subHours(4),
                'updated_at' => now()->subHours(4),
            ],
            [
                'actor_user_id' => $operator->id,
                'module' => 'student_deletion',
                'action' => 'student.suspend',
                'target_account_id' => 'demo_1002',
                'payload' => json_encode([
                    'external_account_id' => 'demo_1002',
                    'operation_id' => $opWithFailures,
                    'queued' => true,
                ]),
                'correlation_id' => $corr(),
                'ip_address' => '198.51.100.5',
                'success' => true,
                'error_message' => null,
                'created_at' => now()->subHours(4),
                'updated_at' => now()->subHours(4),
            ],
        ]);
    }
}
