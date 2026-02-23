<?php

namespace App\Domains\Device\Services;

use App\Domains\Device\Models\Device;
use App\Domains\Device\Models\DeviceToken;
use App\Domains\Device\Repositories\DeviceRepository;
use App\Shared\Helpers\TransactionHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

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
            if (array_key_exists('password', $data) || array_key_exists('device_key', $data)) {
                DeviceToken::where('device_id', $device->id)->delete();
            }
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

    /**
     * Authenticate device by device_key or by name + password.
     * Returns device and token on success; throws on failure.
     *
     * @return array{device: Device, token: string}
     * @throws \Exception
     */
    public function authenticateDevice(array $credentials): array
    {
        $device = null;

        if (!empty($credentials['device_key'])) {
            $device = $this->repository->findByDeviceKey($credentials['device_key']);
            if (!$device) {
                throw new \Exception('Invalid device key');
            }
        } elseif (!empty($credentials['name']) && array_key_exists('password', $credentials)) {
            $device = $this->repository->findByName($credentials['name']);
            if (!$device) {
                throw new \Exception('Invalid device name or password');
            }
            $password = $credentials['password'] ?? '';
            $storedPlain = $device->decrypted_password;
            if ($storedPlain === null || $password !== $storedPlain) {
                throw new \Exception('Invalid device name or password');
            }
        } else {
            throw new \Exception('Provide either device_key or name and password');
        }

        if ($device->status === Device::STATUS_MAINTENANCE) {
            throw new \Exception('Device is in maintenance and cannot authenticate');
        }

        return TransactionHelper::execute(function () use ($device) {
            // One token per device: delete existing tokens for this device
            DeviceToken::where('device_id', $device->id)->delete();

            $expiresAt = now()->addDays(30);
            $tokenModel = DeviceToken::create([
                'device_id' => $device->id,
                'token' => Str::random(64),
                'expires_at' => $expiresAt,
            ]);

            return [
                'device' => $device,
                'token' => $tokenModel->token,
            ];
        });
    }

    /**
     * Regenerate device_key for a device. Revokes all device tokens.
     * Returns the new device_key once (for admin to give to device).
     */
    public function regenerateDeviceKey(Device $device): string
    {
        return TransactionHelper::execute(function () use ($device) {
            DeviceToken::where('device_id', $device->id)->delete();
            $newKey = Str::random(64);
            $device->update(['device_key' => $newKey]);
            return $newKey;
        });
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
