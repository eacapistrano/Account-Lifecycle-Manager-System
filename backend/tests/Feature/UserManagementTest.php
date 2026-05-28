<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_user_that_can_log_in(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $viewerRole = Role::query()->where('slug', 'viewer')->firstOrFail();

        $response = $this->postJson('/api/authorization/users', [
            'name' => 'New Operator',
            'email' => 'new.operator@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role_id' => $viewerRole->id,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.email', 'new.operator@example.com')
            ->assertJsonPath('data.role.slug', 'viewer');

        $user = User::query()->where('email', 'new.operator@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('SecurePass123!', $user->password));

        $this->postJson('/api/login', [
            'email' => 'new.operator@example.com',
            'password' => 'SecurePass123!',
            'device_name' => 'test-client',
        ])
            ->assertOk()
            ->assertJsonStructure(['token']);
    }

    public function test_user_creation_requires_users_manage_permission(): void
    {
        Sanctum::actingAs(User::factory()->viewer()->create());
        $viewerRole = Role::query()->where('slug', 'viewer')->firstOrFail();

        $this->postJson('/api/authorization/users', [
            'name' => 'Blocked Operator',
            'email' => 'blocked.operator@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'role_id' => $viewerRole->id,
        ])->assertForbidden();
    }
}
