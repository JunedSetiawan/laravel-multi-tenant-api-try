<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Artisan;

class TenantManagementController extends Controller
{
    /**
     * Create new tenant
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'db_host' => 'nullable|string',
            'db_port' => 'nullable|integer',
            'db_name' => 'nullable|string',
            'db_username' => 'nullable|string',
            'db_password' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Create tenant instance (not saved yet)
            $tenant = new Tenant([
                'id' => \Illuminate\Support\Str::slug($request->name) . '-' . uniqid(),
                'name' => $request->name,
            ]);

            // Set custom database config if provided BEFORE saving
            // If db_name not provided, generate one based on tenant ID
            $dbName = $request->input('db_name', 'tenant_' . $tenant->id);
            $tenant->setInternal('db_name', $dbName);

            if ($request->filled('db_host')) {
                $tenant->setInternal('db_host', $request->db_host);
            }
            if ($request->filled('db_port')) {
                $tenant->setInternal('db_port', $request->db_port);
            }
            if ($request->filled('db_username')) {
                $tenant->setInternal('db_username', $request->db_username);
            }
            if ($request->filled('db_password')) {
                // Encrypt database password for security
                $tenant->setInternal('db_password', encrypt($request->db_password));
            }

            // Generate API Key before saving
            $apiKey = 'tk_' . bin2hex(random_bytes(32));
            $tenant->setInternal('api_key', hash('sha256', $apiKey));

            // Save the tenant - this will trigger TenantCreated event
            // which will create database and run migrations automatically
            $tenant->save();

            DB::commit();

            return response()->json([
                'message' => 'Tenant created successfully',
                'data' => [
                    'tenant_id' => $tenant->id,
                    'name' => $tenant->name,
                    'api_key' => $apiKey,
                    'database' => $tenant->database()->getName(),
                ],
                'warning' => 'Save the API Key securely! It cannot be retrieved again.'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Failed to create tenant',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all tenants
     */
    public function index()
    {
        $tenants = Tenant::all()->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'database' => $tenant->database()->getName(),
                'created_at' => $tenant->created_at,
            ];
        });

        return response()->json([
            'data' => $tenants,
            'total' => $tenants->count()
        ]);
    }

    /**
     * Get specific tenant
     */
    public function show($id)
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found'
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'database' => [
                    'name' => $tenant->database()->getName(),
                    'username' => $tenant->database()->getUsername(),
                ],
                'created_at' => $tenant->created_at,
            ]
        ]);
    }

    /**
     * Regenerate API Key
     */
    public function regenerateApiKey($id)
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found'
            ], 404);
        }

        $apiKey = $tenant->generateApiKey();

        return response()->json([
            'message' => 'API Key regenerated successfully',
            'data' => [
                'tenant_id' => $tenant->id,
                'api_key' => $apiKey,
            ],
            'warning' => 'Update this key in your application immediately!'
        ]);
    }

    /**
     * Health check
     */
    public function healthCheck($id)
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found'
            ], 404);
        }

        try {
            tenancy()->initialize($tenant);
            DB::connection('tenant')->getPdo();

            $status = [
                'status' => 'healthy',
                'tenant_id' => $tenant->id,
                'database' => DB::connection('tenant')->getDatabaseName(),
                'timestamp' => now(),
            ];

            tenancy()->end();

            return response()->json($status);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update tenant
     */
    public function update(Request $request, $id)
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'db_host' => 'nullable|string',
            'db_port' => 'nullable|integer',
            'db_name' => 'nullable|string',
            'db_username' => 'nullable|string',
            'db_password' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        try {
            if ($request->filled('name')) {
                $tenant->name = $request->name;
                $tenant->save();
            }

            if ($request->filled('db_host')) {
                $tenant->setInternal('db_host', $request->db_host);
            }
            if ($request->filled('db_port')) {
                $tenant->setInternal('db_port', $request->db_port);
            }
            if ($request->filled('db_name')) {
                $tenant->setInternal('db_name', $request->db_name);
            }
            if ($request->filled('db_username')) {
                $tenant->setInternal('db_username', $request->db_username);
            }
            if ($request->filled('db_password')) {
                // Encrypt database password for security
                $tenant->setInternal('db_password', encrypt($request->db_password));
            }

            // Save the updated tenant
            $tenant->save();

            return response()->json([
                'message' => 'Tenant updated successfully',
                'data' => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'database' => [
                        'name' => $tenant->database()->getName(),
                        'username' => $tenant->database()->getUsername(),
                    ],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update tenant',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete tenant
     */
    public function destroy($id)
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found'
            ], 404);
        }

        try {
            $tenant->delete();

            return response()->json([
                'message' => 'Tenant deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete tenant',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function testConnection($id)
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return response()->json([
                'error' => 'Tenant not found'
            ], 404);
        }

        try {
            tenancy()->initialize($tenant);
            DB::connection('tenant')->getPdo();

            tenancy()->end();
            return response()->json([
                'status' => 'success',
                'message' => 'Connection successful',
                'database' => DB::connection('tenant')->getDatabaseName(),
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to connect to tenant database',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
