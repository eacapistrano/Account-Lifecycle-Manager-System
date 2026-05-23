<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuthorizationUserController;
use App\Http\Controllers\Api\AutomationQueueController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PolicyController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\SuspendedAccountController;
use App\Http\Controllers\GoogleUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/delete-google-user', [GoogleUserController::class, 'deleteUser']);

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'throttle:api', 'audit.append'])->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/dashboard', DashboardController::class);

    Route::get('/students', [StudentController::class, 'index']);
    Route::get('/students/actions', [StudentController::class, 'operationHistory']);
    Route::middleware(['permission:audit.export', 'throttle:sensitive'])->group(function (): void {
        Route::get('/students/actions/export/csv', [StudentController::class, 'operationHistoryExportCsv']);
        Route::get('/students/actions/export/pdf', [StudentController::class, 'operationHistoryExportPdf']);
    });
    Route::get('/students/actions/{operationId}/failures', [StudentController::class, 'operationFailures']);
    Route::get('/students/actions/{operationId}', [StudentController::class, 'operationStatus']);

    Route::get('/suspended-accounts', [SuspendedAccountController::class, 'index']);
    Route::get('/suspended', [SuspendedAccountController::class, 'index']);

    Route::get('/policies', [PolicyController::class, 'index']);
    Route::get('/policies/{policy}', [PolicyController::class, 'show']);
    Route::get('/policies/{policy}/next-run', [PolicyController::class, 'nextRun']);

    Route::get('/automation/queue', [AutomationQueueController::class, 'index']);

    Route::get('/audit-events', [AuditLogController::class, 'index']);
    Route::get('/audit', [AuditLogController::class, 'index']);

    Route::get('/user', fn (Request $request) => $request->user());

    Route::middleware(['permission:roles.view,users.manage'])->group(function (): void {
        Route::get('/authorization/roles', [RoleController::class, 'index']);
    });

    Route::middleware(['permission:roles.view,roles.manage'])->group(function (): void {
        Route::get('/authorization/permissions', [PermissionController::class, 'index']);
    });

    Route::middleware(['permission:roles.manage', 'throttle:sensitive'])->group(function (): void {
        Route::post('/authorization/roles', [RoleController::class, 'store']);
        Route::patch('/authorization/roles/{role}', [RoleController::class, 'update']);
        Route::delete('/authorization/roles/{role}', [RoleController::class, 'destroy']);
    });

    Route::middleware(['permission:users.manage', 'throttle:sensitive'])->group(function (): void {
        Route::get('/authorization/users', [AuthorizationUserController::class, 'index']);
        Route::patch('/authorization/users/{user}/role', [AuthorizationUserController::class, 'updateRole']);
    });

    Route::middleware(['permission:student_import.run', 'throttle:sensitive'])->group(function (): void {
        Route::post('/students/import', [StudentController::class, 'import']);
        Route::post('/students/sync', [StudentController::class, 'sync']);
    });

    Route::middleware(['permission:student.bulk_suspend', 'throttle:sensitive'])->group(function (): void {
        Route::post('/students/suspend', [StudentController::class, 'suspend']);
        Route::post('/students/unsuspend', [StudentController::class, 'unsuspend']);
    });

    Route::middleware(['permission:student.bulk_delete', 'throttle:sensitive'])->group(function (): void {
        Route::post('/students/delete', [StudentController::class, 'delete']);
    });

    Route::middleware(['permission:suspended.priority', 'throttle:sensitive'])->group(function (): void {
        Route::patch('/suspended-accounts/{student}', [SuspendedAccountController::class, 'updatePriority']);
        Route::patch('/suspended/{student}', [SuspendedAccountController::class, 'updatePriority']);
    });

    Route::middleware(['permission:policy.write', 'throttle:sensitive'])->group(function (): void {
        Route::post('/policies', [PolicyController::class, 'store']);
        Route::patch('/policies/{policy}', [PolicyController::class, 'update']);
        Route::delete('/policies/{policy}', [PolicyController::class, 'destroy']);
        Route::post('/automation/queue/dispatch', [AutomationQueueController::class, 'dispatch']);
    });

    Route::middleware(['permission:audit.export', 'throttle:sensitive'])->group(function (): void {
        Route::get('/audit-events/export/csv', [AuditLogController::class, 'exportCsv']);
        Route::get('/audit-events/export/pdf', [AuditLogController::class, 'exportPdf']);
        Route::get('/audit/export.csv', [AuditLogController::class, 'exportCsv']);
        Route::get('/audit/export.pdf', [AuditLogController::class, 'exportPdf']);
    });
});
