<?php

namespace App\Domains\Mood\Models;

use App\Domains\Device\Models\Device;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoodCounterFeedback extends Model
{
    use HasTenant;

    protected $table = 'mood_counter_feedback';

    protected $fillable = [
        'client_uuid',
        'session_id',
        'tenant_id',
        'ticket_id',
        'counter_id',
        'officer_id',
        'device_id',
        'rating_option_id',
        'rating_score',
        'reason_id',
        'comment',
        'submitted_at',
        'synced_from_offline',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'synced_from_offline' => 'boolean',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(MoodFeedbackSession::class, 'session_id', 'id');
    }
}
