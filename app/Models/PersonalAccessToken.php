<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use App\Models\Traits\UsesTenantConnection;
use Illuminate\Support\Facades\Log;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use UsesTenantConnection;

    /**
     * Find the token instance matching the given token.
     *
     * @param  string  $token
     * @return static|null
     */
    public static function findToken($token)
    {
        Log::info('PersonalAccessToken::findToken called', [
            'token' => substr($token, 0, 10) . '...',
            'connection' => (new static)->getConnectionName(),
            'tenancy_initialized' => tenancy()->initialized,
            'current_tenant' => tenancy()->tenant?->id,
        ]);

        if (strpos($token, '|') === false) {
            return static::where('token', hash('sha256', $token))->first();
        }

        [$id, $token] = explode('|', $token, 2);

        $instance = static::find($id);

        Log::info('Token lookup result', [
            'token_id' => $id,
            'found' => $instance ? 'yes' : 'no',
            'connection_used' => (new static)->getConnectionName(),
        ]);

        if ($instance && hash_equals($instance->token, hash('sha256', $token))) {
            return $instance;
        }

        return null;
    }
}
