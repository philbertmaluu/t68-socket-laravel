<?php

namespace App\Shared\Traits;

use Illuminate\Database\Eloquent\Builder;

trait HasTenant
{
    protected static function bootHasTenant(): void
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id) && $tenantId = self::getCurrentTenantId()) {
                $model->tenant_id = $tenantId;
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder) {
            if ($tenantId = self::getCurrentTenantId()) {
                $table = $builder->getModel()->getTable();
                $builder->where($table . '.tenant_id', $tenantId);
            }
        });
    }

    protected static function getCurrentTenantId(): ?string
    {
        try {
            return app()->bound('tenant.id') ? app('tenant.id') : null;
        } catch (\Exception $e) {
            return null;
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
