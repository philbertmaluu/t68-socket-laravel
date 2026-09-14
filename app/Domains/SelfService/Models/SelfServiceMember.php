<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;

class SelfServiceMember extends Model
{
    use HasTenant;

    protected $table = 'self_service_members';

    protected $fillable = [
        'tenant_id',
        'member_number',
        'member_name',
        'phone',
        'email',
        'active',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }
}
