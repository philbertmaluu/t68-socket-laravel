<?php

namespace App\Domains\Tenant\Services;

use App\Domains\Tenant\Models\Tenant;
use App\Domains\Tenant\Repositories\TenantRepository;
use App\Shared\Helpers\TransactionHelper;
use App\Shared\Helpers\UuidHelper;
use Illuminate\Database\Eloquent\Collection;

class TenantService
{
    private TenantRepository $repository;

    public function __construct()
    {
        $this->repository = new TenantRepository();
    }

    public function findById(int|string $id, bool $withTrashed = false): ?Tenant
    {
        return $this->repository->findById($id, $withTrashed);
    }

    public function findAll(array $filters = []): Collection
    {
        return $this->repository->findAll($filters);
    }

    public function findByDomain(string $domain): ?Tenant
    {
        return $this->repository->findByDomain($domain);
    }

    public function createTenant(array $data): Tenant
    {
        $this->validateTenantData($data);
        $data['id'] = $data['id'] ?? UuidHelper::generate();

        return TransactionHelper::execute(function () use ($data) {
            return $this->repository->create($data);
        });
    }

    public function updateTenant(Tenant $tenant, array $data): Tenant
    {
        $this->validateTenantData($data, $tenant);

        return TransactionHelper::execute(function () use ($tenant, $data) {
            return $this->repository->update($tenant, $data);
        });
    }

    public function deleteTenant(Tenant $tenant, bool $force = false): bool
    {
        return TransactionHelper::execute(function () use ($tenant, $force) {
            return $this->repository->delete($tenant, $force);
        });
    }

    public function restoreTenant(Tenant $tenant): bool
    {
        return TransactionHelper::execute(function () use ($tenant) {
            return $this->repository->restore($tenant);
        });
    }

    public function paginate(int $perPage = 15, int $page = 1, array $filters = []): array
    {
        return $this->repository->paginate($perPage, $page, $filters);
    }

    private function validateTenantData(array $data, ?Tenant $tenant = null): void
    {
        if (isset($data['domain'])) {
            $existing = $this->repository->findByDomain($data['domain']);
            if ($existing && (!$tenant || $existing->id !== $tenant->id)) {
                throw new \InvalidArgumentException('Domain already exists');
            }
        }
    }
}
