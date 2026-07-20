<?php

namespace App\Domains\Mood\Models;

use App\Domains\Device\Models\Device;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoodOfflineSyncRecord extends Model
{
    protected $table = 'mood_offline_sync_queue';

    protected $fillable = [
        'client_uuid',
        'device_id',
        'feedback_type',
        'payload',
        'status',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }
}
