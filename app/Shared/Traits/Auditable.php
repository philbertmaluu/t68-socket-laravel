<?php

namespace App\Shared\Traits;

use App\Domains\Audit\Models\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    /**
     * Boot the auditable trait.
     */
    protected static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            self::audit('created', $model);
        });

        static::updated(function (Model $model) {
            self::audit('updated', $model);
        });

        static::deleted(function (Model $model) {
            if ($model->isForceDeleting()) {
                self::audit('force_deleted', $model);
            } else {
                self::audit('deleted', $model);
            }
        });

        static::restored(function (Model $model) {
            self::audit('restored', $model);
        });
    }

    /**
     * Create an audit trail entry.
     */
    protected static function audit(string $event, Model $model): void
    {
        try {
            $user = Auth::user();
            $oldValues = $event === 'updated' ? $model->getOriginal() : null;
            $newValues = in_array($event, ['created', 'updated', 'restored']) ? $model->getAttributes() : null;

            AuditTrail::create([
                'tenant_id' => self::getTenantId($model),
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'event' => $event,
                'user_id' => $user?->id ?? null,
                'user_type' => $user ? get_class($user) : null,
                'old_values' => $oldValues ? self::filterAuditableValues($oldValues) : null,
                'new_values' => $newValues ? self::filterAuditableValues($newValues) : null,
                'url' => Request::fullUrl(),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create audit trail', [
                'error' => $e->getMessage(),
                'model' => get_class($model),
                'event' => $event,
            ]);
        }
    }

    /**
     * Get tenant ID from model.
     */
    protected static function getTenantId(Model $model): ?string
    {
        if (method_exists($model, 'getTenantId')) {
            return $model->getTenantId();
        }

        return $model->tenant_id ?? app('tenant.id');
    }

    /**
     * Filter out sensitive or unnecessary values from audit trail.
     */
    protected static function filterAuditableValues(array $values): array
    {
        $hidden = ['password', 'remember_token', 'api_token'];
        
        return array_filter($values, function ($key) use ($hidden) {
            return !in_array($key, $hidden) && !str_ends_with($key, '_at');
        }, ARRAY_FILTER_USE_KEY);
    }
}
