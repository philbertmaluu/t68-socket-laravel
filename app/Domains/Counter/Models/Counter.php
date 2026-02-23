<?php

namespace App\Domains\Counter\Models;

use App\Shared\Traits\Auditable;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Domains\Tenant\Models\Tenant;

class Counter extends Model
{
    use HasFactory, HasTenant, SoftDeletes, Auditable;

    protected $table = 'counters';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'counter_type_id',
        'status',
        'office_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /** One counter has many services via counter_services (counter_id, service_id, office_id). */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domains\Service\Models\Service::class,
            'counter_services',
            'counter_id',
            'service_id',
            'id',
            'id'
        )->using(CounterServicePivot::class)->withPivot('office_id', 'tenant_id')->withTimestamps();
    }

    public function counterType(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\CounterType\Models\CounterType::class, 'counter_type_id', 'id');
    }

    public static function getStatuses(): array
    {
        return ['ACTIVE', 'INACTIVE', 'MAINTENANCE'];
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function isInactive(): bool
    {
        return $this->status === 'INACTIVE';
    }

    public function isMaintenance(): bool
    {
        return $this->status === 'MAINTENANCE';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'INACTIVE');
    }

    public function scopeMaintenance($query)
    {
        return $query->where('status', 'MAINTENANCE');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('counter_type_id', $type);
    }

    public function scopeForService($query, string $serviceId)
    {
        return $query->whereHas('services', fn ($q) => $q->where('services.id', $serviceId));
    }

    public function counterClerks(): HasMany
    {
        return $this->hasMany(CounterClerk::class, 'counter_id', 'id');
    }

    public function activeCounterClerks(): HasMany
    {
        return $this->hasMany(CounterClerk::class, 'counter_id', 'id')->where('is_active', true);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }
}
