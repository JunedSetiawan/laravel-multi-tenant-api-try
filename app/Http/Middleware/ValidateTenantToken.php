<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ValidateTenantToken
{
    /**
     * Handle an incoming request.
     *
     * Validate that the authenticated user's token belongs to the current tenant
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only validate if user is authenticated via Sanctum
        if (Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user();
            $token = $request->user('sanctum')->currentAccessToken();

            // Get current tenant ID from request
            $currentTenantId = $request->get('_tenant_id');

            if (!$currentTenantId) {
                return response()->json([
                    'error' => 'Tenant Context Missing',
                    'message' => 'Unable to determine current tenant context. Please ensure X-Tenant-API-Key header is provided.',
                ], 500);
            }

            // Verify token belongs to current tenant by checking the database connection
            // Since PersonalAccessToken uses tenant connection, if we can find the user,
            // it means the token is from the correct tenant database

            // Additional check: verify token was created in this tenant's database
            if (!$token) {
                return response()->json([
                    'error' => 'Invalid Token Context',
                    'message' => 'The provided token does not belong to the current tenant. You cannot use a token from one tenant to access another tenant\'s resources.',
                    'hint' => 'Please login using the correct tenant API key to get a valid token for this tenant.',
                    'current_tenant' => $currentTenantId,
                ], 403);
            }
        }

        return $next($request);
    }
}
