<?php

namespace App\Domains\Notification\Models;

use App\Domains\Tenant\Models\Tenant;
use App\Shared\Traits\Auditable;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationTemplate extends Model
{
    use HasFactory, HasTenant, SoftDeletes, Auditable;

    protected $table = 'notification_templates';

    protected $fillable = [
        'tenant_id',
        'key',
        'channel',
        'locale',
        'subject',
        'body',
        'description',
        'active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Override HasTenant boot:
     * - New templates created in the UI become tenant-scoped
     * - Editing a global (null) template must NOT rewrite tenant_id
     * - Listing includes global + current-tenant rows
     */
    protected static function bootHasTenant(): void
    {
        static::creating(function ($model) {
            if ($model->tenant_id === null || $model->tenant_id === '') {
                $tenantId = self::getCurrentTenantId();
                if (!empty($tenantId)) {
                    $model->tenant_id = $tenantId;
                }
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = self::getCurrentTenantId();
            if (empty($tenantId)) {
                return;
            }

            $table = $builder->getModel()->getTable();
            $builder->where(function (Builder $query) use ($table, $tenantId) {
                $query->where($table . '.tenant_id', $tenantId)
                    ->orWhereNull($table . '.tenant_id');
            });
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }
}

