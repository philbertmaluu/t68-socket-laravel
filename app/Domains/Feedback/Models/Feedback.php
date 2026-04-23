<?php

namespace App\Domains\Feedback\Models;

use App\Domains\Ticket\Models\Ticket;
use App\Domains\Tenant\Models\Tenant;
use App\Shared\Traits\Auditable;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feedback extends Model
{
    use HasFactory, HasTenant, SoftDeletes, Auditable;

    public const TYPE_GENERAL = 'general';
    public const TYPE_TICKET = 'ticket';

    protected $table = 'feedbacks';

    protected $fillable = [
        'tenant_id',
        'feedback_type',
        'rating',
        'comment_key',
        'comment_label',
        'comment_text',
        'ticket_id',
        'ticket_number',
        'clerk_id',
        'clerk_rating',
        'office_id',
        'source',
        'submitted_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'clerk_rating' => 'integer',
            'submitted_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'id');
    }
}
