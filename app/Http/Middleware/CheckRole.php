<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $roles = func_get_args();
        array_shift($roles); // Remove $request from arguments
        if (!$request->user()) {
            return response()->json([
                'error' => 'Unauthenticated'
            ], 401);
        }

        if (!$request->user()->hasAnyRole($roles)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'You do not have permission to access this resource'
            ], 403);
        }

        return $next($request);
    }
}
