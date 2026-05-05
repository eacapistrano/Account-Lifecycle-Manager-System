<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Permission::query()->orderBy('name')->get();

        return response()->json(['data' => $rows]);
    }
}
