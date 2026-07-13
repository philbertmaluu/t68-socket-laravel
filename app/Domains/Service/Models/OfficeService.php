<?php

namespace App\Domains\Service\Models;

use App\Shared\Traits\Auditable;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OfficeService extends Model
{
    use HasFactory, HasTenant, SoftDeletes, Auditable;

    protected $table = 'office_services';
    protected $primaryKey = 'id';

    protected $fillable = [
        'tenant_id',
        'office_id',
        'office_name',
        'region_id',
        'region_name',
        'service_id',
        'service_name',
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id', 'id');
    }

    public function scopeForOffice($query, string $officeId)
    {
        return $query->where('office_id', $officeId);
    }
}
