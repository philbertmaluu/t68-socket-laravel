<?php

namespace App\Domains\Device\Repositories;

use App\Domains\Device\Models\Device;
use App\Shared\Helpers\PaginationHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeviceRepository
{
    public function findById(int|string $id, bool $withTrashed = false): ?Device
    {
        $query = Device::query();
        
        if ($withTrashed) {
            $query->withTrashed();
        }
        
        return $query->find($id);
    }

    public function findAll(array $filters = []): Collection
    {
        $query = Device::query();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['region_id'])) {
            $query->where('region_id', $filters['region_id']);
        }

        if (isset($filters['office_id'])) {
            $query->where('office_id', $filters['office_id']);
        }

        if (isset($filters['serial_number'])) {
            $query->where('serial_number', $filters['serial_number']);
        }

        if (isset($filters['with_trashed']) && $filters['with_trashed']) {
            $query->withTrashed();
        } elseif (isset($filters['only_trashed']) && $filters['only_trashed']) {
            $query->onlyTrashed();
        }

        return $query->get();
    }

    public function findBySerialNumber(string $serialNumber): ?Device
    {
        return Device::where('serial_number', $serialNumber)->first();
    }

    public function findByDeviceKey(string $deviceKey): ?Device
    {
        $normalized = strtoupper(trim($deviceKey));
        if ($normalized === '') {
            return null;
        }

        // Exact match first (fast path — keys are stored uppercase).
        $device = Device::where('device_key', $normalized)->first();
        if ($device) {
            return $device;
        }

        // Fallback for mixed-case / whitespace-polluted legacy rows.
        $device = Device::query()
            ->whereRaw('UPPER(TRIM(device_key)) = ?', [$normalized])
            ->first();
        if ($device) {
            return $device;
        }

        $this->logDeviceKeyMiss($deviceKey, $normalized);

        return null;
    }

    /**
     * Explain a failed device-key lookup without changing the login result.
     * Written to storage/logs so a valid-looking key can be compared with what this database actually has.
     */
    private function logDeviceKeyMiss(string $raw, string $normalized): void
    {
        $tenantId = null;
        if (app()->bound('tenant.id') && !empty(app('tenant.id'))) {
            $tenantId = app('tenant.id');
        }

        $user = Auth::user();
        if ($tenantId === null && $user && !empty($user->tenant_id)) {
            $tenantId = $user->tenant_id;
        }

        $context = [
            'reason' => 'key_not_in_this_database',
            'device_key_raw' => $raw,
            'device_key_normalized' => $normalized,
            'device_key_length' => strlen($normalized),
            // Letter O is 79, digit 0 is 48. Makes lookalike typos visible in the log.
            'device_key_char_codes' => array_map('ord', str_split($normalized)),
            'tenant_scope_id' => $tenantId,
            'auth_user_id' => $user?->id,
            'db_connection' => DB::connection()->getName(),
            'db_driver' => DB::connection()->getDriverName(),
            'db_database' => DB::connection()->getDatabaseName(),
        ];

        try {
            $unscoped = Device::withoutGlobalScope('tenant')
                ->where(function ($query) use ($normalized) {
                    $query->where('device_key', $normalized)
                        ->orWhereRaw('UPPER(TRIM(device_key)) = ?', [$normalized]);
                })
                ->first();

            if ($unscoped) {
                $context['reason'] = 'hidden_by_tenant_scope';
                $context['matched_device_id'] = $unscoped->id;
                $context['matched_device_name'] = $unscoped->name;
                $context['matched_tenant_id'] = $unscoped->tenant_id;
            } else {
                $trashed = Device::withoutGlobalScope('tenant')
                    ->withTrashed()
                    ->where(function ($query) use ($normalized) {
                        $query->where('device_key', $normalized)
                            ->orWhereRaw('UPPER(TRIM(device_key)) = ?', [$normalized]);
                    })
                    ->first();

                if ($trashed) {
                    $context['reason'] = 'device_soft_deleted';
                    $context['matched_device_id'] = $trashed->id;
                    $context['matched_device_name'] = $trashed->name;
                    $context['matched_tenant_id'] = $trashed->tenant_id;
                    $context['matched_deleted_at'] = $trashed->deleted_at?->toDateTimeString();
                }
            }
        } catch (\Throwable $e) {
            $context['reason'] = 'lookup_diagnostic_failed';
            $context['error'] = $e->getMessage();
        }

        Log::warning('device.auth.invalid_device_key', $context);
    }

    public function findByName(string $name): ?Device
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }

        return Device::where('name', $trimmed)->first()
            ?? Device::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($trimmed)])->first();
    }

    public function create(array $data): Device
    {
        return Device::create($data);
    }

    public function update(Device $device, array $data): Device
    {
        $device->update($data);
        return $device->fresh();
    }

    public function delete(Device $device, bool $force = false): bool
    {
        if ($force) {
            return $device->forceDelete();
        }
        return $device->delete();
    }

    public function restore(Device $device): bool
    {
        return $device->restore();
    }

    public function paginate(int $perPage = 15, int $page = 1, array $filters = []): array
    {
        [$page, $perPage] = PaginationHelper::validateParams($page, $perPage);
        $query = Device::query();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['region_id'])) {
            $query->where('region_id', $filters['region_id']);
        }

        if (isset($filters['office_id'])) {
            $query->where('office_id', $filters['office_id']);
        }

        if (isset($filters['serial_number'])) {
            $query->where('serial_number', $filters['serial_number']);
        }

        if (isset($filters['with_trashed']) && $filters['with_trashed']) {
            $query->withTrashed();
        } elseif (isset($filters['only_trashed']) && $filters['only_trashed']) {
            $query->onlyTrashed();
        }

        $total = $query->count();
        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get();
        $meta = PaginationHelper::calculateMeta($total, $perPage, $page);

        return [
            'data' => $items,
            'meta' => $meta,
        ];
    }
}
