<?php

namespace App\Domains\Ticket\Models;

use Illuminate\Database\Eloquent\Model;

class OfficeAnnounceLock extends Model
{
    protected $table = 'office_announce_locks';

    protected $primaryKey = 'office_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'office_id',
        'current_announce_id',
        'is_announcing',
        'started_at',
    ];

    protected $casts = [
        'is_announcing' => 'boolean',
        'started_at' => 'datetime',
    ];
}
