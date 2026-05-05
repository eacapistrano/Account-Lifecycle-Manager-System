<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::query()->with('permissions')->orderBy('name')->get();

        return response()->json(['data' => $roles]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();
        /** @var list<string> $slugs */
        $slugs = $data['permission_slugs'];

        $role = Role::query()->create([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'is_system' => false,
        ]);

        $ids = Permission::query()->whereIn('slug', $slugs)->pluck('id')->all();
        $role->permissions()->sync($ids);

        return response()->json(['data' => $role->load('permissions')], 201);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $data = $request->validated();

        if ($role->is_system && isset($data['slug']) && $data['slug'] !== $role->slug) {
            return response()->json(['message' => 'System role slugs cannot be changed.'], 422);
        }

        if (isset($data['name'])) {
            $role->name = $data['name'];
        }
        if (! $role->is_system && isset($data['slug'])) {
            $role->slug = $data['slug'];
        }
        $role->save();

        if (isset($data['permission_slugs'])) {
            /** @var list<string> $slugs */
            $slugs = $data['permission_slugs'];
            if ($role->slug === 'admin') {
                $required = Permission::query()->pluck('slug')->all();
                $missing = array_diff($required, $slugs);
                if ($missing !== []) {
                    return response()->json(['message' => 'The administrator role must retain all permissions.'], 422);
                }
            }

            $ids = Permission::query()->whereIn('slug', $slugs)->pluck('id')->all();
            $role->permissions()->sync($ids);
        }

        return response()->json(['data' => $role->fresh()->load('permissions')]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json(['message' => 'System roles cannot be deleted.'], 422);
        }
        if ($role->users()->exists()) {
            return response()->json(['message' => 'Reassign users before deleting this role.'], 422);
        }
        $role->permissions()->detach();
        $role->delete();

        return response()->json(['ok' => true]);
    }
}
