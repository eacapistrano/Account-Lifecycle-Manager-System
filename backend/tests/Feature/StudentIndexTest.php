<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_filters_by_graduation_status(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Student::factory()->create([
            'primary_email' => 'grad@example.com',
            'graduation_status' => 'graduated',
        ]);
        Student::factory()->create([
            'primary_email' => 'enrolled@example.com',
            'graduation_status' => 'enrolled',
        ]);

        $response = $this->getJson('/api/students?graduation_status=graduated');

        $response->assertOk();
        $emails = collect($response->json('data.data'))->pluck('primary_email')->all();
        $this->assertSame(['grad@example.com'], $emails);
    }

    public function test_index_filters_by_email(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Student::factory()->create([
            'primary_email' => 'ava.chen@school.example',
            'full_name' => 'Ava Chen',
        ]);
        Student::factory()->create([
            'primary_email' => 'noah.patel@school.example',
            'full_name' => 'Noah Patel',
        ]);

        $response = $this->getJson('/api/students?email=ava.chen');

        $response
            ->assertOk()
            ->assertJsonPath('data.data.0.primary_email', 'ava.chen@school.example')
            ->assertJsonCount(1, 'data.data');
    }

    public function test_index_search_matches_columns_across_the_registry_row(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Student::factory()->create([
            'primary_email' => 'hidden@example.com',
            'full_name' => 'Hidden Person',
            'department' => 'Science',
            'external_account_id' => 'acct-hidden',
        ]);
        Student::factory()->create([
            'primary_email' => 'visible@example.com',
            'full_name' => 'Arts Alumni',
            'department' => 'Arts',
            'school_year' => '2024',
            'graduation_status' => 'graduated',
            'external_account_id' => 'acct-visible-2024',
        ]);

        $this->getJson('/api/students?search=Arts')
            ->assertOk()
            ->assertJsonPath('data.data.0.primary_email', 'visible@example.com');

        $this->getJson('/api/students?search=2024')
            ->assertOk()
            ->assertJsonPath('data.data.0.primary_email', 'visible@example.com');

        $this->getJson('/api/students?search=acct-visible-2024')
            ->assertOk()
            ->assertJsonPath('data.data.0.primary_email', 'visible@example.com');
    }

    public function test_index_search_can_match_suspended_status_label(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Student::factory()->suspended()->create([
            'primary_email' => 'suspended@example.com',
            'full_name' => 'Suspended User',
        ]);
        Student::factory()->create([
            'primary_email' => 'active@example.com',
            'full_name' => 'Active User',
            'suspended' => false,
        ]);

        $response = $this->getJson('/api/students?search=suspended');

        $response->assertOk();
        $emails = collect($response->json('data.data'))->pluck('primary_email')->all();
        $this->assertContains('suspended@example.com', $emails);
        $this->assertNotContains('active@example.com', $emails);
    }
}
