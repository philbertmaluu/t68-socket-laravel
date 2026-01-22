<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class TenantService
{
    /**
     * Set the current tenant ID.
     *
     * @param string|null $tenantId
     * @return void
     */
    public static function setTenant(?string $tenantId): void
    {
        app()->instance('tenant.id', $tenantId);
    }

    /**
     * Get the current tenant ID.
     *
     * @return string|null
     */
    public static function getTenant(): ?string
    {
        return app('tenant.id');
    }

    /**
     * Clear the current tenant.
     *
     * @return void
     */
    public static function clearTenant(): void
    {
        app()->forgetInstance('tenant.id');
    }

    /**
     * Check if a tenant is set.
     *
     * @return bool
     */
    public static function hasTenant(): bool
    {
        return !empty(self::getTenant());
    }
}
