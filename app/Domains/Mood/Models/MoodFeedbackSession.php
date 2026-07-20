<?php

namespace App\Domains\Mood\Models;

use App\Domains\Device\Models\Device;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoodFeedbackSession extends Model
{
    use HasTenant;

    protected $table = 'mood_feedback_sessions';

    public $incrementing = false;
    protected $keyType = 'string';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'id',
        'tenant_id',
        'ticket_id',
        'counter_id',
        'officer_id',
        'branch_id',
        'device_id',
        'service_id',
        'customer_type',
        'start_time',
        'expire_time',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'expire_time' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }

    public function isExpired(): bool
    {
        return $this->expire_time !== null && $this->expire_time->isPast();
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_ACTIVE], true) && !$this->isExpired();
    }
}
