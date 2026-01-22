<?php

namespace App\Domains\Tenant\Repositories;

use App\Domains\Tenant\Models\Tenant;
use App\Shared\Helpers\PaginationHelper;
use Illuminate\Database\Eloquent\Collection;

class TenantRepository
{
    public function findById(string $id): ?Tenant
    {
        try {
            return Tenant::withoutTenant()->find($id);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to find tenant: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findAll(array $filters = []): Collection
    {
        try {
            $query = Tenant::withoutTenant()->query();

            if (isset($filters['is_active'])) {
                $query->where('is_active', $filters['is_active']);
            }

            if (isset($filters['domain'])) {
                $query->where('domain', 'like', '%' . $filters['domain'] . '%');
            }

            return $query->get();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to find tenants: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findByDomain(string $domain): ?Tenant
    {
        try {
            return Tenant::withoutTenant()->where('domain', $domain)->first();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to find tenant by domain: ' . $e->getMessage(), 0, $e);
        }
    }

    public function create(array $data): Tenant
    {
        try {
            return Tenant::withoutTenant()->create($data);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to create tenant: ' . $e->getMessage(), 0, $e);
        }
    }

    public function update(Tenant $tenant, array $data): Tenant
    {
        try {
            $tenant->update($data);
            return $tenant->fresh();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to update tenant: ' . $e->getMessage(), 0, $e);
        }
    }

    public function delete(Tenant $tenant): bool
    {
        try {
            return $tenant->delete();
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to delete tenant: ' . $e->getMessage(), 0, $e);
        }
    }

    public function paginate(int $perPage = 15, int $page = 1, array $filters = []): array
    {
        try {
            [$page, $perPage] = PaginationHelper::validateParams($page, $perPage);
            $query = Tenant::withoutTenant()->query();

            if (isset($filters['is_active'])) {
                $query->where('is_active', $filters['is_active']);
            }

            $total = $query->count();
            $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get();
            $meta = PaginationHelper::calculateMeta($total, $perPage, $page);

            return [
                'data' => $items,
                'meta' => $meta,
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to paginate tenants: ' . $e->getMessage(), 0, $e);
        }
    }
}
