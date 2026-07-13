<?php

namespace App\Domains\Service\Services;

use App\Domains\Service\Models\Service;
use App\Domains\Service\Repositories\OfficeServiceRepository;
use App\Domains\Service\Repositories\ServiceRepository;
use App\Shared\Helpers\TransactionHelper;
use App\Traits\UserOfficeTrait;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ServiceService
{
    use UserOfficeTrait;

    private ServiceRepository $repository;
    private OfficeServiceRepository $officeServiceRepository;

    public function __construct()
    {
        $this->repository = new ServiceRepository();
        $this->officeServiceRepository = new OfficeServiceRepository();
    }

    public function findById(int|string $id, bool $withTrashed = false): ?Service
    {
        return $this->repository->findById($id, $withTrashed);
    }

    /**
     * Find catalog service and attach current-office assignment fields for API responses.
     */
    public function findAssignedForCurrentOffice(int|string $id): ?array
    {
        $location = $this->getUserOfficeAndRegionFromHrp();
        $officeId = (string) $location['office_id'];

        $assignment = $this->officeServiceRepository->findByOfficeAndService($officeId, $id);
        if (!$assignment) {
            return null;
        }

        $assignment->loadMissing('service');
        return $this->officeServiceRepository->toApiRow($assignment);
    }

    public function findAll(array $filters = []): Collection
    {
        return $this->repository->findAll($filters);
    }

    /**
     * Assign an existing catalog service to the authenticated user's office.
     */
    public function createService(array $data): array
    {
        return TransactionHelper::execute(function () use ($data) {
            $location = $this->getUserOfficeAndRegionFromHrp();
            $officeId = (string) $location['office_id'];
            $serviceId = $data['service_id'];

            $service = $this->repository->findById($serviceId);
            if (!$service) {
                throw new NotFoundHttpException('Selected service not found in catalog');
            }

            $existing = $this->officeServiceRepository->findByOfficeAndService($officeId, $service->id, true);
            if ($existing && $existing->deleted_at === null) {
                throw new \RuntimeException('This service is already assigned to your office');
            }

            if ($existing && $existing->trashed()) {
                $existing->restore();
                $existing->update([
                    'office_name' => $location['office_name'] ?? null,
                    'region_id' => (string) $location['region_id'],
                    'region_name' => $location['region_name'] ?? null,
                    'service_name' => $service->name,
                    'tenant_id' => $service->tenant_id,
                ]);
                $assignment = $existing->fresh(['service']);
            } else {
                $assignment = $this->officeServiceRepository->create([
                    'tenant_id' => $service->tenant_id,
                    'office_id' => $officeId,
                    'office_name' => $location['office_name'] ?? null,
                    'region_id' => (string) $location['region_id'],
                    'region_name' => $location['region_name'] ?? null,
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                ]);
                $assignment->setRelation('service', $service);
            }

            return $this->officeServiceRepository->toApiRow($assignment);
        });
    }

    /**
     * Global predefined catalog for the Add Service picker (excludes already-assigned).
     */
    public function listCatalogForCurrentOffice(): array
    {
        $location = $this->getUserOfficeAndRegionFromHrp();
        return $this->repository->listCatalog((string) $location['office_id']);
    }

    /**
     * Update catalog fields and keep office_services.service_name in sync.
     */
    public function updateService(Service $service, array $data): array
    {
        return TransactionHelper::execute(function () use ($service, $data) {
            $location = $this->getUserOfficeAndRegionFromHrp();
            $officeId = (string) $location['office_id'];

            $assignment = $this->officeServiceRepository->findByOfficeAndService($officeId, $service->id);
            if (!$assignment) {
                throw new NotFoundHttpException('Service is not assigned to your office');
            }

            $catalogPayload = array_intersect_key($data, array_flip([
                'name',
                'swahili_name',
                'description',
                'estimated_time',
                'status',
            ]));

            if ($catalogPayload !== []) {
                $service = $this->repository->update($service, $catalogPayload);
            }

            if (array_key_exists('name', $catalogPayload)) {
                $this->officeServiceRepository->syncServiceNameForService($service->id, $service->name);
                $assignment->service_name = $service->name;
            }

            $assignment->setRelation('service', $service);

            return $this->officeServiceRepository->toApiRow($assignment->fresh(['service']) ?? $assignment);
        });
    }

    /**
     * Soft-delete the office assignment for the current office (catalog row kept).
     */
    public function deleteService(Service $service, bool $force = false): bool
    {
        return TransactionHelper::execute(function () use ($service) {
            $location = $this->getUserOfficeAndRegionFromHrp();
            $officeId = (string) $location['office_id'];

            return $this->officeServiceRepository->deleteByOfficeAndService($officeId, $service->id);
        });
    }

    public function restoreService(Service $service): bool
    {
        return TransactionHelper::execute(function () use ($service) {
            return $this->repository->restore($service);
        });
    }

    /**
     * Paginate services assigned to the authenticated user's office.
     */
    public function paginate(int $perPage = 15, int $page = 1, array $filters = []): array
    {
        $location = $this->getUserOfficeAndRegionFromHrp();
        $officeId = (string) $location['office_id'];

        return $this->officeServiceRepository->paginateForOffice($perPage, $page, $officeId, $filters);
    }

    /**
     * List active services for public/kiosk via office_services.
     */
    public function listPublic(int $perPage = 500, int $page = 1, ?string $officeId = null): array
    {
        return $this->officeServiceRepository->listPublic($perPage, $page, $officeId);
    }

    public function isServiceAssignedToOffice(int|string $serviceId, string $officeId): bool
    {
        return $this->officeServiceRepository->existsForOfficeAndService($officeId, $serviceId);
    }
}
