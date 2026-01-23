<?php

namespace App\Domains\Authentication\Models;

use App\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserRole extends Model
{
    use HasFactory, Auditable, SoftDeletes;

    protected $fillable = [
        'id',
        'user_id',
        'role_id',
        'start_date',
        'end_date',
        'status',
        'handover_to_user_id',
        'handover_date',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'handover_date' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    public function handoverTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handover_to_user_id', 'id');
    }

    public function handovers(): HasMany
    {
        return $this->hasMany(Handover::class, 'user_role_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeHandover($query)
    {
        return $query->where('status', 'handover');
    }

   

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }
}
