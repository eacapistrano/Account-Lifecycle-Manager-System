<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
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

        $this->assertSame(30, Student::query()->count());

        $this->assertDatabaseHas('students', [
            'external_account_id' => 'demo_1001',
            'degree_program' => 'BSc Natural Sciences',
            'graduation_status' => 'enrolled',
        ]);

        $this->assertDatabaseHas('policies', ['name' => 'Suspend graduated — Arts 2025']);
        $this->assertDatabaseCount('bulk_action_operations', 6);
        $this->assertDatabaseCount('audit_events', 7);

        $this->assertSame(2, User::query()->count());
    }
}
