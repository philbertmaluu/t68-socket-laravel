<?php

namespace App\Domains\Service\ServiceDocument\Models;

use App\Shared\Traits\Auditable;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceDocument extends Model
{
    use HasFactory, HasTenant, SoftDeletes, Auditable;

    protected $table = 'service_documents';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'service_id',
        'document_name',
        'is_required',
        'order_index',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'order_index' => 'integer',
            'created_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Service\Models\Service::class, 'service_id', 'id');
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeOptional($query)
    {
        return $query->where('is_required', false);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_index');
    }

    public function isRequired(): bool
    {
        return $this->is_required === true;
    }

    public function isOptional(): bool
    {
        return $this->is_required === false;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Tenant\Models\Tenant::class, 'tenant_id', 'id');
    }
}
