<?php

namespace App\Domains\CounterType\Models;

use App\Shared\Traits\Auditable;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CounterType extends Model
{
    use HasFactory, HasTenant, SoftDeletes, Auditable;

    protected $table = 'counter_types';
    protected $primaryKey = 'id';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Tenant\Models\Tenant::class, 'tenant_id', 'id');
    }

    public function counters(): HasMany
    {
        return $this->hasMany(\App\Domains\Counter\Models\Counter::class, 'type', 'id');
    }
}
