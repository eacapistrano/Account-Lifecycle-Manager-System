<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_role_assigns_permissions(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/authorization/roles', [
            'slug' => 'helpdesk',
            'name' => 'Help desk',
            'permission_slugs' => ['roles.view', 'audit.export'],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('roles', ['slug' => 'helpdesk', 'name' => 'Help desk']);
        $role = Role::query()->where('slug', 'helpdesk')->first();
        $this->assertNotNull($role);
        $this->assertCount(2, $role->permissions);
    }

    public function test_cannot_delete_system_role(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $viewer = Role::query()->where('slug', 'viewer')->firstOrFail();

        $this->deleteJson("/api/authorization/roles/{$viewer->id}")
            ->assertStatus(422);
    }

    public function test_user_without_users_manage_cannot_assign_roles(): void
    {
        $custom = Role::query()->create([
            'slug' => 'readonly_ops',
            'name' => 'Read-only ops',
            'is_system' => false,
        ]);
        $viewPerm = Permission::query()->where('slug', 'roles.view')->firstOrFail();
        $custom->permissions()->attach($viewPerm->id);

        $user = User::factory()->create(['role_id' => $custom->id]);
        Sanctum::actingAs($user);
        $target = User::factory()->viewer()->create();

        $this->patchJson("/api/authorization/users/{$target->id}/role", [
            'role_id' => $custom->id,
        ])->assertForbidden();
    }

    public function test_users_manage_can_reassign_role(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();
        $target = User::factory()->viewer()->create();

        $this->patchJson("/api/authorization/users/{$target->id}/role", [
            'role_id' => $adminRole->id,
        ])->assertOk();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'role_id' => $adminRole->id]);
    }
}
