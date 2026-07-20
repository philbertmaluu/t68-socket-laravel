<?php

namespace App\Domains\Mood\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MoodConfiguration extends Model
{
    use HasTenant, SoftDeletes;

    protected $table = 'mood_configurations';

    protected $fillable = [
        'tenant_id',
        'locale',
        'theme',
        'company',
        'messages',
        'timeouts',
        'features',
        'version',
        'active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'theme' => 'array',
            'company' => 'array',
            'messages' => 'array',
            'timeouts' => 'array',
            'features' => 'array',
            'active' => 'boolean',
        ];
    }
}
