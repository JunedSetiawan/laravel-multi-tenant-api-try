<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Register new user
     */
    public function register(RegisterRequest $request)
    {
        // Debug: Cek koneksi database yang digunakan
        Log::info('Register - Current DB Connection', [
            'default_connection' => config('database.default'),
            'tenant_initialized' => tenancy()->initialized,
            'tenant_id' => tenant('id'),
            'database' => DB::connection()->getDatabaseName(),
            'user_connection' => (new User)->getConnectionName(),
        ]);

        // Force menggunakan connection tenant
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'username' => explode('@', $request->email)[0],
            'password' => Hash::make($request->password),
            'uuid' => \Illuminate\Support\Str::uuid(),
            'role' => $request->role ?? 'receptionist',
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'token' => $token,
            'tenant' => [
                'id' => tenant('id'),
                'name' => tenant('name'),
                'database' => DB::connection()->getDatabaseName(),
            ],
        ], 201);
    }

    /**
     * Login user
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => 'Invalid credentials'
            ], 401);
        }

        // Delete old tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token,
            'tenant' => [
                'id' => tenant('id'),
                'name' => tenant('name'),
            ],
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful'
        ]);
    }

    /**
     * Get current user
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
            'tenant' => [
                'id' => tenant('id'),
                'name' => tenant('name'),
            ],
        ]);
    }
}
