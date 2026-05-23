<?php

namespace Tests\Feature;

use App\Contracts\GoogleWorkspaceUserDeleter;
use App\Jobs\ProcessBulkAccountActionJob;
use App\Models\AuditEvent;
use App\Models\BulkActionOperation;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\StudentAccountLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class StudentGoogleWorkspaceDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['google_workspace.delete_user_key' => 'external_account_id']);
    }

    public function test_delete_removes_student_after_workspace_deleter_succeeds(): void
    {
        config(['google_workspace.delete_enabled' => true]);

        $mock = Mockery::mock(GoogleWorkspaceUserDeleter::class);
        $mock->shouldReceive('deleteUser')->once()->with('ext-sync-001');
        $this->instance(GoogleWorkspaceUserDeleter::class, $mock);

        $student = Student::factory()->create([
            'external_account_id' => 'ext-sync-001',
        ]);

        app(StudentAccountLifecycleService::class)->deleteByExternalAccountId('ext-sync-001');

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_delete_resolves_student_by_primary_email_when_bulk_identifier_is_email(): void
    {
        config(['google_workspace.delete_enabled' => true]);

        $mock = Mockery::mock(GoogleWorkspaceUserDeleter::class);
        $mock->shouldReceive('deleteUser')->once()->with('ext-by-email');
        $this->instance(GoogleWorkspaceUserDeleter::class, $mock);

        $student = Student::factory()->create([
            'external_account_id' => 'ext-by-email',
            'primary_email' => 'lookup.by.email@school.test',
        ]);

        app(StudentAccountLifecycleService::class)->deleteByExternalAccountId('lookup.by.email@school.test');

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_delete_uses_primary_email_when_configured(): void
    {
        config([
            'google_workspace.delete_enabled' => true,
            'google_workspace.delete_user_key' => 'primary_email',
        ]);

        $mock = Mockery::mock(GoogleWorkspaceUserDeleter::class);
        $mock->shouldReceive('deleteUser')->once()->with('student@school.test');
        $this->instance(GoogleWorkspaceUserDeleter::class, $mock);

        $student = Student::factory()->create([
            'external_account_id' => 'id-xyz',
            'primary_email' => 'student@school.test',
        ]);

        app(StudentAccountLifecycleService::class)->deleteByExternalAccountId('id-xyz');

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_delete_skips_workspace_when_disabled_and_still_removes_student(): void
    {
        config(['google_workspace.delete_enabled' => false]);

        $student = Student::factory()->create();

        app(StudentAccountLifecycleService::class)->deleteByExternalAccountId($student->external_account_id);

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_delete_does_not_remove_student_when_workspace_deleter_fails(): void
    {
        config(['google_workspace.delete_enabled' => true]);

        $mock = Mockery::mock(GoogleWorkspaceUserDeleter::class);
        $mock->shouldReceive('deleteUser')->andThrow(new RuntimeException('Google API unavailable'));
        $this->instance(GoogleWorkspaceUserDeleter::class, $mock);

        $student = Student::factory()->create([
            'external_account_id' => 'ext-fail',
        ]);

        $caught = false;
        try {
            app(StudentAccountLifecycleService::class)->deleteByExternalAccountId('ext-fail');
        } catch (RuntimeException $e) {
            $caught = true;
            $this->assertSame('Google API unavailable', $e->getMessage());
        }

        $this->assertTrue($caught);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_bulk_delete_job_deletes_multiple_workspace_users(): void
    {
        config([
            'google_workspace.delete_enabled' => true,
            'google_workspace.delete_user_key' => 'external_account_id',
        ]);

        $keys = [];
        $mock = Mockery::mock(GoogleWorkspaceUserDeleter::class);
        $mock->shouldReceive('deleteUser')->andReturnUsing(function (string $key) use (&$keys): void {
            $keys[] = $key;
        });
        $this->instance(GoogleWorkspaceUserDeleter::class, $mock);

        $s1 = Student::factory()->create(['external_account_id' => 'bulk-a']);
        $s2 = Student::factory()->create(['external_account_id' => 'bulk-b']);

        $operationId = (string) Str::uuid();
        Cache::put('bulk_action_status:'.$operationId, [
            'operation_id' => $operationId,
            'action' => 'delete',
            'status' => 'queued',
            'total' => 2,
            'processed' => 0,
            'ok' => 0,
            'failed' => 0,
            'requested_at' => now()->toIso8601String(),
            'started_at' => null,
            'updated_at' => now()->toIso8601String(),
            'completed_at' => null,
        ], 86400);

        BulkActionOperation::query()->create([
            'operation_id' => $operationId,
            'action' => 'delete',
            'status' => 'queued',
            'total' => 2,
            'processed' => 0,
            'ok' => 0,
            'failed' => 0,
            'actor_user_id' => null,
            'requested_at' => now(),
        ]);

        $job = new ProcessBulkAccountActionJob('delete', ['bulk-a', 'bulk-b'], $operationId);
        $job->handle(app(StudentAccountLifecycleService::class), app(AuditLogger::class));

        $this->assertEquals(['bulk-a', 'bulk-b'], $keys);
        $this->assertDatabaseMissing('students', ['id' => $s1->id]);
        $this->assertDatabaseMissing('students', ['id' => $s2->id]);
        $this->assertDatabaseHas('audit_events', [
            'module' => 'student_deletion',
            'action' => 'student.delete',
            'target_account_id' => 'bulk-a',
        ]);
        $this->assertSame(
            $s1->primary_email,
            AuditEvent::query()
                ->where('target_account_id', 'bulk-a')
                ->firstOrFail()
                ->payload['primary_email']
        );
    }

    public function test_delete_dry_run_skips_workspace_and_preserves_student(): void
    {
        config([
            'google_workspace.delete_enabled' => true,
            'security.student_delete_dry_run' => true,
        ]);

        $mock = Mockery::mock(GoogleWorkspaceUserDeleter::class);
        $mock->shouldNotReceive('deleteUser');
        $this->instance(GoogleWorkspaceUserDeleter::class, $mock);

        $student = Student::factory()->create(['external_account_id' => 'dry-run-id']);

        app(StudentAccountLifecycleService::class)->deleteByExternalAccountId('dry-run-id');

        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }
}
