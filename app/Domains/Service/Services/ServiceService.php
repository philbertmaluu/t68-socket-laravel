<?php

namespace App\Domains\Service\Services;

use App\Domains\Service\Models\Service;
use App\Domains\Service\Repositories\ServiceRepository;
use App\Shared\Helpers\TransactionHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ServiceService
{
    private ServiceRepository $repository;

    public function __construct()
    {
        $this->repository = new ServiceRepository();
    }

    public function findById(int|string $id, bool $withTrashed = false): ?Service
    {
        return $this->repository->findById($id, $withTrashed);
    }

    public function findAll(array $filters = []): Collection
    {
        return $this->repository->findAll($filters);
    }

    public function createService(array $data): Service
    {
        return TransactionHelper::execute(function () use ($data) {
            
            // get office and region from auth user if not provided on the request ($data)
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                throw new \Exception('User not authenticated');
            }

            Log::info('User', ['user' => $user]);

            $data['region_id'] = $data['region_id'] ?? $user->office_code;
            $data['office_id'] = $data['office_id'] ?? $user->office_code;

            return $this->repository->create($data);
        });
    }

    public function updateService(Service $service, array $data): Service
    {
        return TransactionHelper::execute(function () use ($service, $data) {
            // get office and region from auth user if not provided on the request ($data)
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                throw new \Exception('User not authenticated');
            }

            $data['region_id'] = $data['region_id'] ?? $user->office_code;
            $data['office_id'] = $data['office_id'] ?? $user->office_code;

            return $this->repository->update($service, $data);
        });
    }

    public function deleteService(Service $service, bool $force = false): bool
    {
        return TransactionHelper::execute(function () use ($service, $force) {
            return $this->repository->delete($service, $force);
        });
    }

    public function restoreService(Service $service): bool
    {
        return TransactionHelper::execute(function () use ($service) {
            return $this->repository->restore($service);
        });
    }

    public function paginate(int $perPage = 15, int $page = 1, array $filters = []): array
    {
        return $this->repository->paginate($perPage, $page, $filters);
    }

    /**
     * List active services for public/kiosk (no Sanctum). Optionally by office_id.
     */
    public function listPublic(int $perPage = 500, int $page = 1, ?string $officeId = null): array
    {
        return $this->repository->listPublic($perPage, $page, $officeId);
    }
}
