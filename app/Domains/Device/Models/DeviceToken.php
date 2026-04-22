<?php

namespace App\Domains\Device\Models;

use App\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    use Auditable;

    protected $table = 'device_tokens';

    protected $fillable = [
        'device_id',
        'token',
        'expires_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Auditable trait calls isForceDeleting() in delete events.
     * DeviceToken does not use SoftDeletes, so treat deletes as force deletes.
     */
    public function isForceDeleting(): bool
    {
        return true;
    }
}
