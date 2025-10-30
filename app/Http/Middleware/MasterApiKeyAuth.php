<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MasterApiKeyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Master-API-Key');

        if (!$apiKey) {
            return response()->json([
                'error' => 'Master API Key is required',
                'message' => 'Please provide X-Master-API-Key header'
            ], 401);
        }

        $masterKey = env('MASTER_API_KEY');

        if (!$masterKey || !hash_equals($masterKey, $apiKey)) {
            return response()->json([
                'error' => 'Invalid Master API Key',
                'message' => 'The provided Master API Key is not valid'
            ], 403);
        }

        return $next($request);
    }
}
