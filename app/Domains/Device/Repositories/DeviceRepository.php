<?php

namespace App\Domains\Device\Repositories;

use App\Domains\Device\Models\Device;
use App\Shared\Helpers\PaginationHelper;
use Illuminate\Database\Eloquent\Collection;

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
