<?php

namespace App\Domains\Mood\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MoodRatingOption extends Model
{
    use HasTenant, SoftDeletes;

    protected $table = 'mood_rating_options';

    protected $fillable = [
        'tenant_id',
        'key',
        'title',
        'emoji',
        'color',
        'score',
        'locale',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }
}
