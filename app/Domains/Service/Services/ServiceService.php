<?php

namespace App\Domains\Service\Services;

use App\Domains\Service\Models\Service;
use App\Domains\Service\Repositories\ServiceRepository;
use App\Shared\Helpers\TransactionHelper;
use App\Shared\Helpers\UuidHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

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
            // Ensure tenant_id is set (required by DB). Prefer authenticated user's tenant_id.
            // This keeps local development simple while still supporting multi-tenancy.
            if (empty($data['tenant_id'])) {
                $user = Auth::user();
                if ($user && !empty($user->tenant_id)) {
                    $data['tenant_id'] = $user->tenant_id;
                } else {
                    // Fallback for local/dev environments if no user tenant is available.
                    $data['tenant_id'] = $data['tenant_id'] ?? 0;
                }
            }

            // Hardcode region_id and office_id for now (will use auth user later)
            // TODO: Get from authenticated user's region/office when available
            $data['region_id'] = '1';
            $data['office_id'] = '1';

            return $this->repository->create($data);
        });
    }

    public function updateService(Service $service, array $data): Service
    {
        return TransactionHelper::execute(function () use ($service, $data) {
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
}
