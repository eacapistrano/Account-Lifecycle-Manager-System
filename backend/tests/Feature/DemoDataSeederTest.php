<?php

namespace Tests\Feature;

use App\Models\Policy;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentGraduationPolicyEvaluator;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_demo_users_students_policies_and_audit_related_rows(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'admin@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'operator@example.com']);

        $this->assertSame(32, Student::query()->count());

        $this->assertDatabaseHas('students', [
            'external_account_id' => 'demo_1001',
            'degree_program' => 'BSc Natural Sciences',
            'graduation_status' => 'enrolled',
        ]);

        $this->assertDatabaseHas('students', [
            'external_account_id' => 'demo_grad_warn',
            'graduation_status' => 'graduated',
        ]);
        $this->assertDatabaseHas('students', [
            'external_account_id' => 'demo_grad_suspend',
            'graduation_status' => 'graduated',
        ]);

        $this->assertDatabaseHas('policies', ['name' => 'Student graduation — 60 day suspend']);
        $this->assertDatabaseHas('policies', ['name' => 'Suspend graduated — Arts 2025']);
        $this->assertDatabaseCount('bulk_action_operations', 6);
        $this->assertDatabaseCount('audit_events', 7);

        $this->assertSame(2, User::query()->count());
    }

    public function test_seeded_graduation_policy_preview_counts_demo_rows(): void
    {
        $this->seed(DemoDataSeeder::class);

        $policy = Policy::query()->where('name', 'Student graduation — 60 day suspend')->firstOrFail();
        $preview = app(StudentGraduationPolicyEvaluator::class)->previewCounts($policy->rule_json);

        $this->assertSame(1, $preview['eligible_warnings']);
        $this->assertSame(1, $preview['eligible_suspensions']);
    }
}
