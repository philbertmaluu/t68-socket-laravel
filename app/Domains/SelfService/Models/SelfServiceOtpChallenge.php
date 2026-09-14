<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Models;

use Illuminate\Database\Eloquent\Model;

class SelfServiceOtpChallenge extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'self_service_otp_challenges';

    protected $fillable = [
        'id',
        'tenant_id',
        'device_id',
        'member_number',
        'member_name',
        'phone',
        'otp_hash',
        'attempts',
        'expires_at',
        'last_sent_at',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
