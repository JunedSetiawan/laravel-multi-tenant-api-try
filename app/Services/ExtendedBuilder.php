<?php

namespace App\Services;

use YasinTgh\LaravelPostman\Collections\Builder as BaseBuilder;

class ExtendedBuilder extends BaseBuilder
{
    /**
     * Override build method to add custom variables
     */
    public function build(array $routes): array
    {
        // Call parent build
        $collection = parent::build($routes);

        // Add custom variables for tenant and master API keys
        $collection['variable'][] = [
            'key' => 'tenant_api_key',
            'value' => '',
            'type' => 'string',
            'description' => 'Tenant API Key (format: tk_...)'
        ];

        $collection['variable'][] = [
            'key' => 'master_api_key',
            'value' => config('tenancy.master_api_key', ''),
            'type' => 'string',
            'description' => 'Master API Key for central management'
        ];

        return $collection;
    }
}
