<?php

namespace App\Domains\Device\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Device extends Model
{
    use HasFactory, HasTenant;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'tenant_id',
        'name',
        'type',
        'status',
        'region_id',
        'office_id',
        'serial_number',
        'ip_address',
        'password',
        'last_seen',
        'notes',
    ];

    protected $casts = [
        'last_seen' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
    ];

    public const TYPE_KIOSK = 'KIOSK';
    public const TYPE_TV = 'TV';

    public const STATUS_ONLINE = 'online';
    public const STATUS_OFFLINE = 'offline';
    public const STATUS_MAINTENANCE = 'maintenance';

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function ($device) {
            if ($device->isDirty('password') && !empty($device->password)) {
                $device->password = Crypt::encryptString($device->password);
            }
        });
    }

    public function getDecryptedPasswordAttribute(): ?string
    {
        if (empty($this->password)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->password);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function isOnline(): bool
    {
        return $this->status === self::STATUS_ONLINE;
    }

    public function isOffline(): bool
    {
        return $this->status === self::STATUS_OFFLINE;
    }

    public function isInMaintenance(): bool
    {
        return $this->status === self::STATUS_MAINTENANCE;
    }

    public function isKiosk(): bool
    {
        return $this->type === self::TYPE_KIOSK;
    }

    public function isTv(): bool
    {
        return $this->type === self::TYPE_TV;
    }

    public function updateLastSeen(): bool
    {
        return $this->update(['last_seen' => now()]);
    }

    public static function getTypes(): array
    {
        return [
            self::TYPE_KIOSK,
            self::TYPE_TV,
        ];
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_ONLINE,
            self::STATUS_OFFLINE,
            self::STATUS_MAINTENANCE,
        ];
    }

    public function scopeOnline($query)
    {
        return $query->where('status', self::STATUS_ONLINE);
    }

    public function scopeOffline($query)
    {
        return $query->where('status', self::STATUS_OFFLINE);
    }

    public function scopeMaintenance($query)
    {
        return $query->where('status', self::STATUS_MAINTENANCE);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForOffice($query, string $officeId)
    {
        return $query->where('office_id', $officeId);
    }

    public function scopeForRegion($query, string $regionId)
    {
        return $query->where('region_id', $regionId);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Tenant\Models\Tenant::class, 'tenant_id', 'id');
    }
}
