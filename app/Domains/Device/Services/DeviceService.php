<?php

namespace App\Domains\Device\Services;

use App\Domains\Device\Models\Device;
use App\Domains\Device\Repositories\DeviceRepository;
use App\Shared\Helpers\TransactionHelper;
use Illuminate\Database\Eloquent\Collection;

class DeviceService
{
    private DeviceRepository $repository;

    public function __construct()
    {
        $this->repository = new DeviceRepository();
    }

    public function findById(int|string $id, bool $withTrashed = false): ?Device
    {
        return $this->repository->findById($id, $withTrashed);
    }

    public function findAll(array $filters = []): Collection
    {
        return $this->repository->findAll($filters);
    }

    public function findBySerialNumber(string $serialNumber): ?Device
    {
        return $this->repository->findBySerialNumber($serialNumber);
    }

    public function createDevice(array $data): Device
    {
        $this->validateDeviceData($data);
        return TransactionHelper::execute(function () use ($data) {
            return $this->repository->create($data);
        });
    }

    public function updateDevice(Device $device, array $data): Device
    {
        $this->validateDeviceData($data, $device);
        return TransactionHelper::execute(function () use ($device, $data) {
            return $this->repository->update($device, $data);
        });
    }

    public function deleteDevice(Device $device, bool $force = false): bool
    {
        return TransactionHelper::execute(function () use ($device, $force) {
            return $this->repository->delete($device, $force);
        });
    }

    public function restoreDevice(Device $device): bool
    {
        return TransactionHelper::execute(function () use ($device) {
            return $this->repository->restore($device);
        });
    }

    public function paginate(int $perPage = 15, int $page = 1, array $filters = []): array
    {
        return $this->repository->paginate($perPage, $page, $filters);
    }

    private function validateDeviceData(array $data, ?Device $device = null): void
    {
        if (isset($data['serial_number'])) {
            $existing = $this->repository->findBySerialNumber($data['serial_number']);
            if ($existing && (!$device || $existing->id !== $device->id)) {
                throw new \InvalidArgumentException('Serial number already exists');
            }
        }
    }
}
