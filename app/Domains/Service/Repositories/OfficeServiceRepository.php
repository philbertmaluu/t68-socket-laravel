<?php

namespace App\Domains\Service\Repositories;

use App\Domains\Service\Models\OfficeService;
use App\Shared\Helpers\PaginationHelper;

class OfficeServiceRepository
{
    public function findById(int|string $id, bool $withTrashed = false): ?OfficeService
    {
        $query = OfficeService::query()->with('service');

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->find($id);
    }

    public function findByOfficeAndService(string $officeId, int|string $serviceId, bool $withTrashed = false): ?OfficeService
    {
        $query = OfficeService::query()
            ->where('office_id', $officeId)
            ->where('service_id', $serviceId);

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->first();
    }

    public function assignedServiceIdsForOffice(string $officeId): array
    {
        return OfficeService::query()
            ->where('office_id', $officeId)
            ->pluck('service_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    public function existsForOfficeAndService(string $officeId, int|string $serviceId): bool
    {
        return OfficeService::query()
            ->where('office_id', $officeId)
            ->where('service_id', $serviceId)
            ->exists();
    }

    public function create(array $data): OfficeService
    {
        return OfficeService::create($data);
    }

    public function update(OfficeService $officeService, array $data): OfficeService
    {
        $officeService->update($data);
        return $officeService->fresh(['service']);
    }

    public function delete(OfficeService $officeService, bool $force = false): bool
    {
        if ($force) {
            return $officeService->forceDelete();
        }

        return $officeService->delete();
    }

    public function deleteByOfficeAndService(string $officeId, int|string $serviceId): bool
    {
        $assignment = $this->findByOfficeAndService($officeId, $serviceId);
        if (!$assignment) {
            return false;
        }

        return (bool) $assignment->delete();
    }

    public function syncServiceNameForService(int|string $serviceId, string $serviceName): void
    {
        OfficeService::query()
            ->where('service_id', $serviceId)
            ->update(['service_name' => $serviceName, 'updated_at' => now()]);
    }

    /**
     * Paginate office assignments joined with catalog service fields.
     * Returns array rows shaped for the admin Services API.
     */
    public function paginateForOffice(int $perPage, int $page, string $officeId, array $filters = []): array
    {
        [$page, $perPage] = PaginationHelper::validateParams($page, $perPage);

        $query = OfficeService::query()
            ->with('service')
            ->where('office_id', $officeId);

        if (!empty($filters['status'])) {
            $query->whereHas('service', fn ($q) => $q->where('status', $filters['status']));
        }

        if (!empty($filters['region_id'])) {
            $query->where('region_id', $filters['region_id']);
        }

        $total = $query->count();
        $items = $query->orderByDesc('created_at')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(fn (OfficeService $row) => $this->toApiRow($row))
            ->values();

        return [
            'data' => $items,
            'meta' => PaginationHelper::calculateMeta($total, $perPage, $page),
        ];
    }

    /**
     * Public/kiosk list: ACTIVE catalog services assigned to an office.
     */
    public function listPublic(int $perPage, int $page, ?string $officeId = null): array
    {
        [$page, $perPage] = PaginationHelper::validateParams($page, $perPage);

        $query = OfficeService::withoutTenant()
            ->with(['service' => fn ($q) => $q->withoutTenant()])
            ->whereHas('service', fn ($q) => $q->withoutTenant()->where('status', 'ACTIVE'));

        if ($officeId !== null && $officeId !== '') {
            $query->where('office_id', $officeId);
        }

        $total = $query->count();
        $items = $query->orderBy('service_name')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(fn (OfficeService $row) => $this->toApiRow($row))
            ->values();

        return [
            'data' => $items,
            'meta' => PaginationHelper::calculateMeta($total, $perPage, $page),
        ];
    }

    public function toApiRow(OfficeService $row): array
    {
        $service = $row->service;

        return [
            'id' => $service?->id ?? $row->service_id,
            'office_service_id' => $row->id,
            'name' => $service?->name ?? $row->service_name,
            'swahili_name' => $service?->swahili_name,
            'description' => $service?->description,
            'estimated_time' => $service?->estimated_time,
            'status' => $service?->status ?? 'ACTIVE',
            'office_id' => $row->office_id,
            'office_name' => $row->office_name,
            'region_id' => $row->region_id,
            'region_name' => $row->region_name,
            'service_id' => $row->service_id,
            'service_name' => $row->service_name,
            'tenant_id' => $service?->tenant_id ?? $row->tenant_id,
            'created_at' => $service?->created_at ?? $row->created_at,
            'updated_at' => $service?->updated_at ?? $row->updated_at,
        ];
    }
}
