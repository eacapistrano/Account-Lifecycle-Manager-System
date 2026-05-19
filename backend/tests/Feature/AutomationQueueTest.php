<?php

namespace Tests\Feature;

use App\Jobs\EvaluatePoliciesJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AutomationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_index_returns_schedule_and_counts(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/automation/queue');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'queue_connection',
                'pending_count',
                'failed_count',
                'schedules',
                'recent_pending',
                'recent_failed',
            ]);

        $scheduleKeys = collect($response->json('schedules'))->pluck('key')->all();
        $this->assertContains('policy_evaluation', $scheduleKeys);
        $this->assertContains('suspended_due_dates', $scheduleKeys);
    }

    public function test_dispatch_queues_policy_evaluation_job(): void
    {
        Queue::fake();
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/automation/queue/dispatch', [
            'task' => 'policy_evaluation',
        ]);

        $response->assertStatus(202)->assertJsonPath('queued', true);
        Queue::assertPushed(EvaluatePoliciesJob::class);
    }
}
