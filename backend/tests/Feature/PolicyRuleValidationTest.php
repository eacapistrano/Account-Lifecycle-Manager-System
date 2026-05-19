<?php

namespace Tests\Feature;

use App\Models\Policy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PolicyRuleValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_rejects_empty_rule_scope(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/policies', [
            'name' => 'Too broad',
            'action' => 'suspend',
            'rule_json' => [],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['rule_json']);
    }

    public function test_store_rejects_blank_rule_scope_fields(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/policies', [
            'name' => 'Whitespace scope',
            'action' => 'suspend',
            'rule_json' => [
                'department' => '   ',
                'school_year' => '',
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['rule_json']);
    }

    public function test_store_accepts_department_scope(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/policies', [
            'name' => 'Dept only',
            'action' => 'suspend',
            'rule_json' => ['department' => 'Science'],
            'cron_expression' => null,
            'execution_at' => null,
            'is_active' => true,
        ]);

        $response->assertCreated()->assertJsonPath('name', 'Dept only');
    }

    public function test_store_accepts_student_graduation_policy(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/policies', [
            'name' => 'Graduation',
            'action' => 'suspend',
            'rule_json' => [
                'type' => 'student_graduation',
                'suspend_after_days' => 60,
                'warning_days_before_suspend' => 14,
            ],
        ]);

        $response->assertCreated();
    }

    public function test_store_rejects_graduation_policy_with_delete_action(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/policies', [
            'name' => 'Invalid graduation',
            'action' => 'delete',
            'rule_json' => [
                'type' => 'student_graduation',
                'suspend_after_days' => 60,
                'warning_days_before_suspend' => 14,
            ],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['action']);
    }

    public function test_patch_rejects_clearing_scope(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $policy = Policy::query()->create([
            'name' => 'Existing',
            'action' => 'suspend',
            'rule_json' => ['department' => 'Science'],
            'execution_at' => null,
            'cron_expression' => null,
            'is_active' => true,
            'last_evaluated_at' => null,
            'last_status' => 'idle',
            'hold_reason' => null,
        ]);

        $response = $this->patchJson('/api/policies/'.$policy->id, [
            'rule_json' => ['department' => '', 'school_year' => ''],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['rule_json']);
    }
}
