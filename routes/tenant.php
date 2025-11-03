<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Tenant API Routes
|--------------------------------------------------------------------------
|
| Here you can register API routes for tenants. These routes are loaded
| by the RouteServiceProvider and all of them will be assigned to the
| "api" middleware group with "tenant" middleware.
|
| All routes here require X-Tenant-API-Key header.
|
*/

// Public routes (tidak perlu login, tapi perlu tenant API key)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Info tenant
Route::get('/info', function () {
    return response()->json([
        'tenant_id' => tenant('id'),
        'tenant_name' => tenant('name'),
        'database' => DB::connection('tenant')->getDatabaseName(),
    ]);
});

// Protected routes (perlu login dengan Sanctum + tenant API key)
Route::middleware(['auth:sanctum', 'tenant.token'])->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Products
    Route::apiResource('products', \App\Http\Controllers\ProductController::class);

    // Admin only routes
    // Route::middleware(['role:admin'])->group(function () {
    //     Route::apiResource('users', UserController::class);
    // });
});
