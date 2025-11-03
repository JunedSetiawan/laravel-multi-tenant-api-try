<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TenantManagementController;
use App\Http\Controllers\TenantBackupController;

// Debug route (tanpa middleware)
Route::get('/debug/tenants', function () {
    $tenants = \App\Models\Tenant::all();

    return response()->json([
        'tenants' => $tenants->map(function ($tenant) {
            $dbConfig = null;
            try {
                $dbConfig = $tenant->database()->getName();
            } catch (\Exception $e) {
                $dbConfig = 'Error: ' . $e->getMessage();
            }

            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'attributes' => $tenant->getAttributes(),
                'internal_keys' => [
                    'db_name' => $tenant->getInternal('db_name'),
                    'db_host' => $tenant->getInternal('db_host'),
                    'db_port' => $tenant->getInternal('db_port'),
                    'db_username' => $tenant->getInternal('db_username'),
                ],
                'database_config_name' => $dbConfig,
            ];
        })
    ]);
});

// Protected dengan Master API Key
Route::middleware(['master.api'])->group(function () {

    // Tenant Management
    Route::post('/tenants', [TenantManagementController::class, 'store']);
    Route::get('/tenants', [TenantManagementController::class, 'index']);
    Route::get('/tenants/{id}', [TenantManagementController::class, 'show']);
    Route::put('/tenants/{id}', [TenantManagementController::class, 'update']);
    Route::delete('/tenants/{id}', [TenantManagementController::class, 'destroy']);
    Route::post('/tenants/{id}/regenerate-key', [TenantManagementController::class, 'regenerateApiKey']);
    Route::get('/tenants/{id}/health', [TenantManagementController::class, 'healthCheck']);

    // test connector
    Route::post('/tenants/{id}/test-connection', [TenantManagementController::class, 'testConnection']);

    // Tenant Backup Management
    Route::post('/tenants/{id}/backup', [TenantBackupController::class, 'backup']);
    Route::get('/tenants/{id}/backups', [TenantBackupController::class, 'list']);
    Route::get('/tenants/{id}/backups/download', [TenantBackupController::class, 'download']);
    Route::delete('/tenants/{id}/backups', [TenantBackupController::class, 'delete']);
    Route::post('/tenants/{id}/restore', [TenantBackupController::class, 'restore']);
    Route::get('/backups/stats', [TenantBackupController::class, 'stats']);
});
