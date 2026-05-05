<?php

namespace Tests\Feature;

use App\Models\Policy;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_aggregates(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Student::factory()->count(2)->create(['suspended' => false]);
        Student::factory()->create([
            'suspended' => true,
            'deletion_scheduled_at' => now()->subDay(),
        ]);
        Student::factory()->create([
            'suspended' => true,
            'deletion_scheduled_at' => now()->addWeek(),
        ]);

        Policy::query()->create([
            'name' => 'Scoped suspend',
            'action' => 'suspend',
            'rule_json' => ['department' => 'Science'],
            'execution_at' => null,
            'cron_expression' => null,
            'is_active' => true,
            'last_evaluated_at' => null,
            'last_status' => 'idle',
            'hold_reason' => null,
        ]);

        Policy::query()->create([
            'name' => 'Inactive',
            'action' => 'suspend',
            'rule_json' => ['department' => 'Arts'],
            'execution_at' => null,
            'cron_expression' => null,
            'is_active' => false,
            'last_evaluated_at' => null,
            'last_status' => 'idle',
            'hold_reason' => null,
        ]);

        $response = $this->getJson('/api/dashboard');

        $response
            ->assertOk()
            ->assertJsonPath('counts.students', 4)
            ->assertJsonPath('counts.suspended', 2)
            ->assertJsonPath('counts.active_policies', 1)
            ->assertJsonPath('counts.due_for_deletion', 1)
            ->assertJsonStructure([
                'counts' => ['students', 'suspended', 'due_for_deletion', 'active_policies'],
                'recent_audit',
                'active_policies',
            ]);
    }
}
