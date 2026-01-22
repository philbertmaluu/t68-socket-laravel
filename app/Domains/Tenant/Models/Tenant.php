<?php

namespace App\Domains\Tenant\Models;

use App\Domains\Counter\Models\Counter;
use App\Domains\CounterType\Models\CounterType;
use App\Domains\Device\Models\Device;
use App\Domains\Service\Models\Service;
use App\Domains\Service\ServiceDocument\Models\ServiceDocument;
use App\Domains\Ticket\Models\Ticket;
use App\Shared\Traits\Auditable;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, HasTenant, SoftDeletes, Auditable;

    protected $table = 'tenants';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'name',
        'domain',
        'database',
        'is_active',
        'settings',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'tenant_id', 'id');
    }

    public function counters(): HasMany
    {
        return $this->hasMany(Counter::class, 'tenant_id', 'id');
    }

    public function counterTypes(): HasMany
    {
        return $this->hasMany(CounterType::class, 'tenant_id', 'id');
    }

    public function serviceDocuments(): HasMany
    {
        return $this->hasMany(ServiceDocument::class, 'tenant_id', 'id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'tenant_id', 'id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'tenant_id', 'id');
    }
}
