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
        // Set created_by before creating
        static::creating(function (Model $model) {
            if (self::hasAuditableField($model, 'created_by')) {
                $user = Auth::user();
                if ($user && empty($model->created_by)) {
                    $model->created_by = $user->id;
                }
            }
        });

        // Set updated_by before updating
        static::updating(function (Model $model) {
            if (self::hasAuditableField($model, 'updated_by')) {
                $user = Auth::user();
                if ($user) {
                    $model->updated_by = $user->id;
                }
            }
        });

        // Set deleted_by before soft deleting
        static::deleting(function (Model $model) {
            if (!$model->isForceDeleting() && self::hasAuditableField($model, 'deleted_by')) {
                $user = Auth::user();
                if ($user) {
                    // Update deleted_by quietly to avoid triggering events
                    $model->updateQuietly(['deleted_by' => $user->id]);
                }
            }
        });

        // Create audit trail entries after model events
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
            // Clear deleted_by on restore
            if (self::hasAuditableField($model, 'deleted_by')) {
                $model->deleted_by = null;
                $model->save();
            }
            self::audit('restored', $model);
        });
    }

    /**
     * Check if model has an auditable field.
     */
    protected static function hasAuditableField(Model $model, string $field): bool
    {
        return in_array($field, $model->getFillable()) || 
               array_key_exists($field, $model->getAttributes());
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
        // Check if model has tenant_id attribute
        if (isset($model->tenant_id)) {
            return $model->tenant_id;
        }

        // Try to get from app container
        if (app()->bound('tenant.id')) {
            return app('tenant.id');
        }

        return null;
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
