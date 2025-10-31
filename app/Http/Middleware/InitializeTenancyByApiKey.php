<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByApiKey
{
    protected $tenancy;

    public function __construct(\Stancl\Tenancy\Tenancy $tenancy)
    {
        $this->tenancy = $tenancy;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get API Key from header
        $apiKey = $request->header('X-Tenant-API-Key');

        if (!$apiKey) {
            return response()->json([
                'error' => 'Tenant API Key Required',
                'message' => 'X-Tenant-API-Key header is missing. Please provide a valid tenant API key to access this resource.',
                'hint' => 'Add header: X-Tenant-API-Key: tk_your_api_key_here'
            ], 401);
        }

        // Find tenant by API Key
        $tenant = Tenant::findByApiKey($apiKey);

        if (!$tenant) {
            return response()->json([
                'error' => 'Invalid Tenant API Key',
                'message' => 'The provided API key does not match any tenant in our system. Please verify your API key or contact your administrator.',
                'hint' => 'Make sure you are using the correct API key for your tenant.',
                'provided_key' => substr($apiKey, 0, 10) . '...' // Show partial key for debugging
            ], 403);
        }

        // Debug: Log tenant info
        Log::info('Initializing Tenancy', [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'db_name' => $tenant->database()->getName(),
            'db_username' => $tenant->database()->getUsername(),
            'tenant_attributes' => $tenant->getAttributes(),
        ]);

        // Initialize tenancy
        $this->tenancy->initialize($tenant);

        // Fallback: Manually ensure tenant connection is set with database name
        if (config('database.default') !== 'tenant') {
            Log::warning('Default connection not set to tenant, forcing it');
            config(['database.default' => 'tenant']);
            DB::purge('tenant');
            DB::setDefaultConnection('tenant');
        }

        // CRITICAL FIX: Manually set all database config from tenant
        // Decrypt password if it exists and is encrypted
        $dbPassword = $tenant->getInternal('db_password');
        if ($dbPassword) {
            try {
                $dbPassword = decrypt($dbPassword);
            } catch (\Exception $e) {
                // If decryption fails, use the value as-is (backward compatibility)
                Log::warning('Failed to decrypt tenant DB password, using as-is', ['tenant' => $tenant->id]);
            }
        } else {
            $dbPassword = config('database.connections.tenant.password');
        }

        Log::info('Tenant DB Password Retrieved', [
            'has_password' => $dbPassword ? true : false,
            'password_length' => $dbPassword ? strlen($dbPassword) : 0,
            'password_encrypt' => $tenant->getInternal('db_password'),
            'password_decrypted' => $dbPassword,
        ]);

        $dbConfig = [
            'database' => $tenant->database()->getName(),
            'host' => $tenant->getInternal('db_host') ?? config('database.connections.tenant.host'),
            'port' => $tenant->getInternal('db_port') ?? config('database.connections.tenant.port'),
            'username' => $tenant->getInternal('db_username') ?? config('database.connections.tenant.username'),
            'password' => $dbPassword,
        ];        // Apply tenant-specific database config
        foreach ($dbConfig as $key => $value) {
            if ($value !== null && config("database.connections.tenant.{$key}") !== $value) {
                config(["database.connections.tenant.{$key}" => $value]);
            }
        }

        // Purge and reconnect to apply the new config
        DB::purge('tenant');
        DB::reconnect('tenant');

        // Debug: Log after initialization
        Log::info('Tenancy Initialized', [
            'tenant_initialized' => $this->tenancy->initialized,
            'default_connection' => config('database.default'),
            'tenant_connection' => config('database.connections.tenant'),
            'central_connection' => config('tenancy.database.central_connection'),
            'template_connection' => config('tenancy.database.template_tenant_connection'),
        ]);

        // Add tenant info to request
        $request->merge([
            '_tenant_id' => $tenant->id,
            '_tenant_name' => $tenant->name,
        ]);

        return $next($request);
    }

    /**
     * Handle the response after request is processed
     */
    public function terminate($request, $response)
    {
        // Clean up tenancy after request
        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }
}
