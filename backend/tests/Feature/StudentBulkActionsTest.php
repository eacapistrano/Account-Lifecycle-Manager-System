<?php

namespace Tests\Feature;

use App\Jobs\ProcessBulkAccountActionJob;
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
            ->assertJsonPath('count', 1);

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
}
