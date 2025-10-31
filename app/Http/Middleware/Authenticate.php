<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // If the request is an API request, don't redirect.
        // The exception handler will return a 401 JSON response.
        if ($request->is('api/*') || $request->expectsJson()) {
            return null;
        }

        return route('login');
    }
}
