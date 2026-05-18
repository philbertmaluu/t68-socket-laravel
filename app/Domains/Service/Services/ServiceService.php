<?php

namespace App\Domains\Service\Services;

use App\Domains\Service\Models\Service;
use App\Domains\Service\Repositories\ServiceRepository;
use App\Shared\Helpers\TransactionHelper;
use App\Traits\UserOfficeTrait;
use Illuminate\Database\Eloquent\Collection;

class ServiceService
{
    use UserOfficeTrait;

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
        return $this->repository->findAll($this->scopeFiltersByHrpOffice($filters));
    }

    public function createService(array $data): Service
    {
        return TransactionHelper::execute(function () use ($data) {
            $data = $this->fillOfficeRegionFromHrp($data);

            return $this->repository->create($data);
        });
    }

    public function updateService(Service $service, array $data): Service
    {
        return TransactionHelper::execute(function () use ($service, $data) {
            $data = $this->fillOfficeRegionFromHrp($data);

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
        return $this->repository->paginate($perPage, $page, $this->scopeFiltersByHrpOffice($filters));
    }

    /**
     * List active services for public/kiosk (no Sanctum). Optionally by office_id.
     */
    public function listPublic(int $perPage = 500, int $page = 1, ?string $officeId = null): array
    {
        return $this->repository->listPublic($perPage, $page, $officeId);
    }
}
