<?php

namespace App\Domains\Counter\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounterClerk extends Model
{
    use HasFactory, HasTenant;

    protected $table = 'counter_clerk';

    protected $fillable = [
        'tenant_id',
        'counter_id',
        'clerk_id',
        'is_active',
        'assigned_at',
        'unassigned_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class, 'counter_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function deactivate(): bool
    {
        return $this->update([
            'is_active' => false,
            'unassigned_at' => now(),
        ]);
    }

    public function activate(): bool
    {
        return $this->update([
            'is_active' => true,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Tenant\Models\Tenant::class, 'tenant_id', 'id');
    }
}
