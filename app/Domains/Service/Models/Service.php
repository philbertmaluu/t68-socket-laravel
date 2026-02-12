<?php

namespace App\Domains\Service\Models;

use App\Domains\Tenant\Models\Tenant;
use App\Domains\Service\ServiceDocument\Models\ServiceDocument;
use App\Shared\Traits\Auditable;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, HasTenant, SoftDeletes, Auditable;

    protected $table = 'services';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'description',
        'estimated_time',
        'status',
        'region_id',
        'office_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'estimated_time' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public static function getStatuses(): array
    {
        return [
            'ACTIVE',
            'INACTIVE',
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

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'INACTIVE');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ServiceDocument::class, 'service_id', 'id')->ordered();
    }

    public function requiredDocuments(): HasMany
    {
        return $this->hasMany(ServiceDocument::class, 'service_id', 'id')->required()->ordered();
    }

    public function optionalDocuments(): HasMany
    {
        return $this->hasMany(ServiceDocument::class, 'service_id', 'id')->optional()->ordered();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }
}
