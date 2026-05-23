<?php

namespace Tests\Feature;

use App\Jobs\ProcessBulkAccountActionJob;
use App\Models\AuditEvent;
use App\Models\BulkActionOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspend_endpoint_queues_bulk_job_and_tracks_operation(): void
    {
        Queue::fake();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/students/suspend', [
            'account_ids' => ['mock:user:1001', 'mock:user:1002'],
        ]);

        $response
            ->assertStatus(202)
            ->assertJsonPath('queued', true)
            ->assertJsonPath('action', 'suspend')
            ->assertJsonPath('count', 2);

        $operationId = (string) $response->json('operation_id');
        $this->assertNotSame('', $operationId);

        Queue::assertPushed(ProcessBulkAccountActionJob::class, function (ProcessBulkAccountActionJob $job) use ($operationId): bool {
            return $job->action === 'suspend'
                && $job->accountIds === ['mock:user:1001', 'mock:user:1002']
                && $job->operationId === $operationId;
        });

        $this->assertDatabaseHas('bulk_action_operations', [
            'operation_id' => $operationId,
            'action' => 'suspend',
            'status' => 'queued',
            'total' => 2,
        ]);
    }

    public function test_delete_endpoint_requires_matching_confirmation_phrase(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/students/delete', [
            'account_ids' => ['mock:user:1001'],
            'confirmation_phrase' => 'NOT THE PHRASE',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Confirmation phrase does not match configured DELETE_CONFIRMATION_PHRASE.');
    }

    public function test_delete_endpoint_queues_bulk_job_and_tracks_operation(): void
    {
        Queue::fake();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/students/delete', [
            'account_ids' => ['mock:user:1003'],
            'confirmation_phrase' => (string) config('security.delete_confirmation_phrase'),
        ]);

        $response
            ->assertStatus(202)
            ->assertJsonPath('queued', true)
            ->assertJsonPath('action', 'delete')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('dry_run', false);

        $operationId = (string) $response->json('operation_id');
        $this->assertNotSame('', $operationId);

        Queue::assertPushed(ProcessBulkAccountActionJob::class, function (ProcessBulkAccountActionJob $job) use ($operationId): bool {
            return $job->action === 'delete'
                && $job->accountIds === ['mock:user:1003']
                && $job->operationId === $operationId;
        });

        $operation = BulkActionOperation::query()
            ->where('operation_id', $operationId)
            ->first();

        $this->assertNotNull($operation);
        $this->assertSame('delete', $operation->action);
        $this->assertSame('queued', $operation->status);
        $this->assertSame(1, $operation->total);
    }

    public function test_delete_endpoint_json_includes_dry_run_flag_when_enabled(): void
    {
        config(['security.student_delete_dry_run' => true]);
        Queue::fake();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/students/delete', [
            'account_ids' => ['mock:user:2001', 'mock:user:2002'],
            'confirmation_phrase' => (string) config('security.delete_confirmation_phrase'),
        ]);

        $response
            ->assertStatus(202)
            ->assertJsonPath('dry_run', true)
            ->assertJsonPath('count', 2);
    }

    public function test_deletion_history_csv_export_lists_deleted_emails_and_dates(): void
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        $requestedAt = now()->subHour();
        $deletedAt = now()->subMinutes(30);
        $operation = BulkActionOperation::query()->create([
            'operation_id' => '11111111-1111-1111-1111-111111111111',
            'action' => 'delete',
            'status' => 'completed',
            'total' => 1,
            'processed' => 1,
            'ok' => 1,
            'failed' => 0,
            'actor_user_id' => $admin->id,
            'requested_at' => $requestedAt,
            'completed_at' => $deletedAt,
        ]);

        AuditEvent::query()->create([
            'actor_user_id' => $admin->id,
            'module' => 'student_deletion',
            'action' => 'student.delete',
            'target_account_id' => 'student-a',
            'payload' => [
                'operation_id' => $operation->operation_id,
                'primary_email' => 'deleted.student@example.edu',
            ],
            'success' => true,
            'created_at' => $deletedAt,
            'updated_at' => $deletedAt,
        ]);

        AuditEvent::query()->create([
            'actor_user_id' => $admin->id,
            'module' => 'student_deletion',
            'action' => 'student.delete',
            'target_account_id' => 'student-b',
            'payload' => [
                'operation_id' => '22222222-2222-2222-2222-222222222222',
                'primary_email' => 'not-in-export@example.edu',
            ],
            'success' => true,
        ]);

        $response = $this->get('/api/students/actions/export/csv?status=completed&from='.$requestedAt->toDateString().'&to='.$requestedAt->toDateString());

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('email,deleted_at', $content);
        $this->assertStringContainsString('deleted.student@example.edu', $content);
        $this->assertMatchesRegularExpression('/deleted\.student@example\.edu,\d{4}-\d{2}-\d{2}T/', $content);
        $this->assertStringNotContainsString('not-in-export@example.edu', $content);
    }
}
