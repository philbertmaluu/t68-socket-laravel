<?php

namespace App\Domains\Device\Models;

use App\Shared\Traits\Auditable;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class Device extends Model
{
    use HasFactory, HasTenant, SoftDeletes, Auditable;

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
        'device_key',
        'counter_id',
        'mood_mode',
        'device_uuid',
        'mood_config',
        'last_seen',
        'notes',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'mood_config' => 'array',
        'last_seen' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
        'device_key',
    ];

    public const TYPE_KIOSK = 'KIOSK';
    public const TYPE_TV = 'TV';
    public const TYPE_MOOD_CHECKER = 'MOOD_CHECKER';

    public const MOOD_MODE_GENERAL = 'GENERAL';
    public const MOOD_MODE_COUNTER = 'COUNTER';

    public const STATUS_ONLINE = 'online';
    public const STATUS_OFFLINE = 'offline';
    public const STATUS_MAINTENANCE = 'maintenance';

    public const DEVICE_KEY_LENGTH_KIOSK = 10;
    public const DEVICE_KEY_LENGTH_MOOD = 5;

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function ($device) {
            if ($device->isDirty('password') && !empty($device->password)) {
                $device->password = Crypt::encryptString($device->password);
            }
            if ($device->exists === false && empty($device->device_key)) {
                $device->device_key = self::generateDeviceKey($device->type);
            }
        });
    }

    public static function deviceKeyLengthForType(?string $type): int
    {
        $normalized = strtoupper(str_replace('-', '_', trim((string) $type)));

        return in_array($normalized, [self::TYPE_MOOD_CHECKER, 'MOOD'], true)
            ? self::DEVICE_KEY_LENGTH_MOOD
            : self::DEVICE_KEY_LENGTH_KIOSK;
    }

    public static function generateDeviceKey(?string $type): string
    {
        return strtoupper(Str::random(self::deviceKeyLengthForType($type)));
    }

    public function deviceKeyLength(): int
    {
        return self::deviceKeyLengthForType($this->type);
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

    public function isMoodChecker(): bool
    {
        return $this->type === self::TYPE_MOOD_CHECKER;
    }

    public function isMoodCounterMode(): bool
    {
        return $this->mood_mode === self::MOOD_MODE_COUNTER;
    }

    public function isMoodGeneralMode(): bool
    {
        return $this->mood_mode === self::MOOD_MODE_GENERAL;
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
            self::TYPE_MOOD_CHECKER,
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

    public function deviceTokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DeviceToken::class, 'device_id', 'id');
    }
}
