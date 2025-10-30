<?php

namespace App\Models\Traits;

use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

trait UsesTenantConnection
{
    /**
     * Get the current connection name for the model.
     *
     * @return string|null
     */
    public function getConnectionName()
    {
        // If tenancy is initialized, use tenant connection
        if (tenancy()->initialized) {
            return 'tenant';
        }

        return parent::getConnectionName();
    }
}
