<?php

namespace App\Domains\Counter\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domains\Tenant\Models\Tenant;

class Counter extends Model
{
    use HasFactory, HasTenant;

    protected $table = 'counters';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'type',
        'service_id',
        'status',
        'office_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Service\Models\Service::class, 'service_id', 'id');
    }

    public function counterType(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\CounterType\Models\CounterType::class, 'type', 'id');
    }

    public static function getStatuses(): array
    {
        return [
            'ACTIVE',
            'INACTIVE',
            'MAINTENANCE',
        ];
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
        return $query->where('type', $type);
    }

    public function scopeForService($query, string $serviceId)
    {
        return $query->where('service_id', $serviceId);
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
