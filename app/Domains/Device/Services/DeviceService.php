<?php

namespace App\Domains\Device\Services;

use App\Domains\Device\Models\Device;
use App\Domains\Device\Models\DeviceToken;
use App\Domains\Device\Repositories\DeviceRepository;
use App\Shared\Helpers\TransactionHelper;
use App\Traits\UserOfficeTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class DeviceService
{
    use UserOfficeTrait;

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
        return $this->repository->findAll($this->scopeFiltersByHrpOffice($filters));
    }

    public function findBySerialNumber(string $serialNumber): ?Device
    {
        return $this->repository->findBySerialNumber($serialNumber);
    }

    public function createDevice(array $data): Device
    {
        $data = $this->fillOfficeRegionFromHrpIfMissing($data);
        $this->validateDeviceData($data);

        return TransactionHelper::execute(function () use ($data) {
            return $this->repository->create($data);
        });
    }

    public function updateDevice(Device $device, array $data): Device
    {
        $data = $this->fillOfficeRegionFromHrpIfMissing($data);
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
        return $this->repository->paginate($perPage, $page, $this->scopeFiltersByHrpOffice($filters));
    }

    /**
     * Authenticate device by device_key or by name + password.
     * Returns device and token on success; throws on failure.
     * Every attempt (success or failure) is written to audit_trails.
     *
     * @return array{device: Device, token: string}
     * @throws \Exception
     */
    public function authenticateDevice(array $credentials): array
    {
        $credentials = $this->normalizeAuthCredentials($credentials);
        $device = null;

        try {
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

            $result = TransactionHelper::execute(function () use ($device) {
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

            DeviceAuthAuditor::success($credentials, $result['device']);

            return $result;
        } catch (\Exception $e) {
            DeviceAuthAuditor::failed(
                $credentials,
                $e->getMessage(),
                $device,
            );
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array<string, mixed>
     */
    private function normalizeAuthCredentials(array $credentials): array
    {
        if (isset($credentials['device_key']) && is_string($credentials['device_key'])) {
            $credentials['device_key'] = strtoupper(trim($credentials['device_key']));
            if ($credentials['device_key'] === '') {
                unset($credentials['device_key']);
            }
        }

        if (isset($credentials['name']) && is_string($credentials['name'])) {
            $credentials['name'] = trim($credentials['name']);
        }

        return $credentials;
    }

    /**
     * Regenerate device_key for a device. Revokes all device tokens.
     * Returns the new device_key once (for admin to give to device).
     */
    public function regenerateDeviceKey(Device $device): string
    {
        return TransactionHelper::execute(function () use ($device) {
            DeviceToken::where('device_id', $device->id)->delete();
            $newKey = strtoupper(Str::random(10));
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


    public function getRegionsFromHrp(): array
    {
        return DB::table('hrpd.region')
            ->select(['region_id as id', 'region_name as name'])
            ->orderBy('region_name')
            ->get()
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
            ])
            ->values()
            ->all();
    }

    public function getOfficesFromHrp(string $regionId): array
    {
        return DB::table('hrpd.office')
            ->select([
                'office_id as id',
                'office_name as name',
                'region_id',
                'office_code',
            ])
            ->where('region_id', $regionId)
            ->orderBy('office_name')
            ->get()
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'name' => (string) $row->name,
                'region_id' => (string) $row->region_id,
                'office_code' => $row->office_code !== null ? (string) $row->office_code : null,
            ])
            ->values()
            ->all();
    }
}
