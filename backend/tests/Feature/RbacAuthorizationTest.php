<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RbacAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_cannot_bulk_suspend(): void
    {
        Sanctum::actingAs(User::factory()->viewer()->create());

        $this->postJson('/api/students/suspend', [
            'account_ids' => ['mock:user:1'],
        ])->assertForbidden();
    }

    public function test_viewer_cannot_export_audit_csv(): void
    {
        Sanctum::actingAs(User::factory()->viewer()->create());

        $this->get('/api/audit-events/export/csv')->assertForbidden();
    }

    public function test_admin_can_list_authorization_roles(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/authorization/roles');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_user_with_only_users_manage_can_list_roles_for_assignment(): void
    {
        $role = Role::query()->create([
            'slug' => 'user_manager',
            'name' => 'User manager',
            'is_system' => false,
        ]);
        $perm = Permission::query()->where('slug', 'users.manage')->firstOrFail();
        $role->permissions()->attach($perm->id);

        $user = User::factory()->create(['role_id' => $role->id]);
        Sanctum::actingAs($user);

        $this->getJson('/api/authorization/roles')->assertOk();
        $this->getJson('/api/authorization/permissions')->assertForbidden();
    }

    public function test_viewer_cannot_list_roles(): void
    {
        Sanctum::actingAs(User::factory()->viewer()->create());

        $this->getJson('/api/authorization/roles')->assertForbidden();
    }
}
