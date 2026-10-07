<?php

use App\Http\Controllers\ClientAdminStaffController;
use App\Http\Controllers\ClientAdminTaskController;
use App\Http\Controllers\ManagerTaskController;
use App\Http\Controllers\OrgnizationController;
use App\Http\Controllers\SuperAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
});

Route::get('/api/auth/csrf', function () {
    return response()->json([
        'csrf_token' => csrf_token(),
    ]);
});

Route::post('/api/auth/login', [SuperAdminController::class, 'login']);
Route::post('/api/auth/logout', [SuperAdminController::class, 'logout'])->middleware('auth');
Route::get('/api/auth/me', [SuperAdminController::class, 'me']);
Route::put('/api/auth/profile', [SuperAdminController::class, 'updateProfile'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->prefix('/api/client-admin/staff')->group(function () {
    Route::get('/{roleId}/trash', [ClientAdminStaffController::class, 'trash']);
    Route::get('/{roleId}', [ClientAdminStaffController::class, 'index']);
    Route::post('/{roleId}', [ClientAdminStaffController::class, 'store']);
    Route::put('/{roleId}/{userId}', [ClientAdminStaffController::class, 'update']);
    Route::delete('/{roleId}/{userId}', [ClientAdminStaffController::class, 'destroy']);
    Route::post('/{roleId}/{userId}/restore', [ClientAdminStaffController::class, 'restore']);
    Route::post('/executives/{userId}/promote', [ClientAdminStaffController::class, 'promote']);
});

Route::middleware('auth:sanctum')->prefix('/api/client-admin/tasks')->group(function () {
    Route::get('/', [ClientAdminTaskController::class, 'index']);
    Route::post('/managers/{managerId}/assign', [ClientAdminTaskController::class, 'assign']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/api/manager/executives', [ManagerTaskController::class, 'executives']);
    Route::get('/api/manager/tasks/available', [ManagerTaskController::class, 'availableTasks']);
    Route::post('/api/manager/tasks/assign', [ManagerTaskController::class, 'assignTasks']);
    Route::get('/api/manager/executives/{executiveId}/tasks', [ManagerTaskController::class, 'tasks']);
    Route::post('/api/manager/executives/{executiveId}/tasks', [ManagerTaskController::class, 'storeTask']);
    Route::put('/api/manager/tasks/{taskId}', [ManagerTaskController::class, 'updateTask']);
    Route::delete('/api/manager/tasks/{taskId}', [ManagerTaskController::class, 'destroyTask']);
    Route::get('/api/executive/tasks', [ManagerTaskController::class, 'executiveTasks']);
});

Route::middleware(['auth', 'auth.super'])->group(function () {
    Route::get('/api/admin/organizations/trash', [OrgnizationController::class, 'trash']);
    Route::get('/api/admin/organizations', [OrgnizationController::class, 'index']);
    Route::post('/api/admin/organizations', [OrgnizationController::class, 'store']);
    Route::put('/api/admin/organizations/{orgnization}', [OrgnizationController::class, 'update']);
    Route::delete('/api/admin/organizations/{orgnization}', [OrgnizationController::class, 'destroy']);
    Route::post('/api/admin/organizations/{organizationId}/restore', [OrgnizationController::class, 'restore']);

    Route::get('/api/admin/clients/trash', [SuperAdminController::class, 'trashedClients']);
    Route::get('/api/admin/clients', [SuperAdminController::class, 'clients']);
    Route::post('/api/admin/clients', [SuperAdminController::class, 'storeClient']);
    Route::put('/api/admin/clients/{client}', [SuperAdminController::class, 'updateClient']);
    Route::delete('/api/admin/clients/{client}', [SuperAdminController::class, 'destroyClient']);
    Route::post('/api/admin/clients/{clientId}/restore', [SuperAdminController::class, 'restoreClient']);

    Route::get('/api/admin/staff/{roleId}', [SuperAdminController::class, 'staff']);
    Route::post('/api/admin/staff/{roleId}', [SuperAdminController::class, 'storeStaff']);
    Route::get('/api/admin/staff/{roleId}/trash', [SuperAdminController::class, 'trashedStaff']);
    Route::post('/api/admin/executives/{user}/promote', [SuperAdminController::class, 'promoteExecutive']);
    Route::put('/api/admin/staff/{roleId}/{user}', [SuperAdminController::class, 'updateStaff']);
    Route::delete('/api/admin/staff/{roleId}/{user}', [SuperAdminController::class, 'destroyStaff']);
    Route::post('/api/admin/staff/{roleId}/{user}/restore', [SuperAdminController::class, 'restoreStaff']);
});

Route::view('/{path}', 'app')->where('path', '^(?!api(?:/|$)).*'); // seprate route for frontend routes and api routes, so that api routes can be handled by the backend and frontend routes can be handled by the frontend.
