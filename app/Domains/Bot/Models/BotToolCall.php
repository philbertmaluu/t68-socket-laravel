<?php

namespace App\Domains\Bot\Models;

use Illuminate\Database\Eloquent\Model;

class BotToolCall extends Model
{
    protected $table = 'bot_tool_calls';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'office_id',
        'role_mode',
        'tool_name',
        'arguments',
        'result_payload',
        'success',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'result_payload' => 'array',
            'success' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
