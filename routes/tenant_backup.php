<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// All API routes now have tenant middleware from the 'api' group
// No need to add 'tenant' middleware again

// Public routes (tidak perlu login)
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

// Protected routes (perlu login dengan Sanctum)
Route::middleware(['auth:sanctum', 'tenant.token'])->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Products
    Route::apiResource('products', \App\Http\Controllers\ProductController::class);


    // Admin only routes
    Route::middleware(['role:admin'])->group(function () {
        Route::apiResource('users', UserController::class);
    });
});
