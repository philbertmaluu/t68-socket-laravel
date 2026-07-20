<?php

namespace App\Domains\Mood\Models;

use App\Domains\Device\Models\Device;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MoodDeviceToken extends Model
{
    use SoftDeletes;

    protected $table = 'mood_device_tokens';

    protected $fillable = [
        'device_id',
        'access_token',
        'refresh_token',
        'device_uuid',
        'access_expires_at',
        'refresh_expires_at',
        'last_used_at',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'access_expires_at' => 'datetime',
            'refresh_expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }

    public function isAccessExpired(): bool
    {
        return $this->access_expires_at !== null && $this->access_expires_at->isPast();
    }

    public function isRefreshExpired(): bool
    {
        return $this->refresh_expires_at !== null && $this->refresh_expires_at->isPast();
    }
}
