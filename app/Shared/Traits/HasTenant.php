<?php

namespace App\Shared\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait HasTenant
{
    protected static function bootHasTenant(): void
    {
        // Automatically set tenant_id on create/update if not already set
        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = self::getCurrentTenantId();
            }
        });

        static::updating(function ($model) {
            // Only set tenant_id on update if it's empty and wasn't originally set
            if (empty($model->tenant_id) && empty($model->getOriginal('tenant_id'))) {
                $model->tenant_id = self::getCurrentTenantId();
            }
        });

        // Global scope to filter by tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            if ($tenantId = self::getCurrentTenantId()) {
                $table = $builder->getModel()->getTable();
                $builder->where($table . '.tenant_id', $tenantId);
            }
        });
    }

    /**
     * Get the current tenant ID from various sources (priority order):
     * 1. App container binding (app('tenant.id'))
     * 2. Authenticated user's tenant_id
     * 3. Default fallback (0 for local dev)
     */
    protected static function getCurrentTenantId()
    {
        try {
            // First, try app container binding (if set by middleware/service)
            if (app()->bound('tenant.id')) {
                $tenantId = app('tenant.id');
                if (!empty($tenantId)) {
                    return $tenantId;
                }
            }

            // Second, try to get from authenticated user
            $user = Auth::user();
            if ($user && !empty($user->tenant_id)) {
                return $user->tenant_id;
            }

            // Fallback for local/dev environments
            return 0;
        } catch (\Exception $e) {
            // If anything fails, return default for local dev
            return 0;
        }
    }

    public function scopeWithoutTenant($query)
    {
        return $query->withoutGlobalScope('tenant');
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->withoutGlobalScope('tenant')->where('tenant_id', $tenantId);
    }
}
