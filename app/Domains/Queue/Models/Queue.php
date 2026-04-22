<?php

namespace App\Domains\Queue\Models;

use App\Domains\Counter\Models\Counter;
use App\Shared\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Queue extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $table = 'queues';
    protected $primaryKey = 'id';

    protected $fillable = [
        'counter_id',
        'name',
        'status',
        'members_waiting',
        'members_being_served',
        'average_wait_time',
        'office_id',
            'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'members_waiting' => 'integer',
            'members_being_served' => 'integer',
            'average_wait_time' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function counter(): BelongsTo
    {
        return $this->belongsTo(Counter::class, 'counter_id', 'id');
    }
}

