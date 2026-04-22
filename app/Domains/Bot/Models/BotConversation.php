<?php

namespace App\Domains\Bot\Models;

use Illuminate\Database\Eloquent\Model;

class BotConversation extends Model
{
    protected $table = 'bot_conversations';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'office_id',
        'role_mode',
        'message',
        'response',
        'tool_calls_count',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'tool_calls_count' => 'integer',
            'meta' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
