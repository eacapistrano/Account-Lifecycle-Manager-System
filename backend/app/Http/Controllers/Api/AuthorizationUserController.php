<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthorizationUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 25), 100);
        $users = User::query()
            ->with('role')
            ->orderBy('email')
            ->paginate($perPage);

        return response()->json(['data' => $users]);
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): JsonResponse
    {
        $roleId = (int) $request->validated('role_id');
        $adminRole = Role::query()->where('slug', 'admin')->first();

        if ($adminRole !== null && $user->role_id === $adminRole->id && $roleId !== $adminRole->id) {
            $hasOtherAdmin = User::query()
                ->where('role_id', $adminRole->id)
                ->where('id', '!=', $user->id)
                ->exists();
            if (! $hasOtherAdmin) {
                return response()->json(['message' => 'Cannot remove the last administrator.'], 422);
            }
        }

        $user->update(['role_id' => $roleId]);

        return response()->json(['data' => $user->fresh()->load('role')]);
    }
}
