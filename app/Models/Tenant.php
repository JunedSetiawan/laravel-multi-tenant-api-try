<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $fillable = [
        'id',
        'name',
    ];

    protected $hidden = [
        'data',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
        ];
    }

    /**
     * Custom database configuration per tenant
     */
    public function database(): \Stancl\Tenancy\DatabaseConfig
    {
        return new \Stancl\Tenancy\DatabaseConfig($this);
    }

    /**
     * Generate API Key untuk tenant
     */
    public function generateApiKey(): string
    {
        $apiKey = 'tk_' . bin2hex(random_bytes(32));
        $this->setInternal('api_key', hash('sha256', $apiKey));
        $this->save();
        return $apiKey;
    }

    /**
     * Verify API Key
     */
    public function verifyApiKey(string $apiKey): bool
    {
        $hashedKey = $this->getInternal('api_key');

        if (!$hashedKey) {
            return false;
        }

        return hash_equals($hashedKey, hash('sha256', $apiKey));
    }

    /**
     * Find tenant by API Key
     */
    public static function findByApiKey(string $apiKey): ?self
    {
        $hashedKey = hash('sha256', $apiKey);

        return self::all()->first(function ($tenant) use ($hashedKey) {
            $tenantHashedKey = $tenant->getInternal('api_key');
            return $tenantHashedKey && hash_equals($tenantHashedKey, $hashedKey);
        });
    }
}
