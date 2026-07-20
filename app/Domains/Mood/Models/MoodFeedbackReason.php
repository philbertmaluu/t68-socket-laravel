<?php

namespace App\Domains\Mood\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MoodFeedbackReason extends Model
{
    use HasTenant, SoftDeletes;

    protected $table = 'mood_feedback_reasons';

    protected $fillable = [
        'tenant_id',
        'key',
        'title',
        'category',
        'applies_to_ratings',
        'locale',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'applies_to_ratings' => 'array',
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }
}
