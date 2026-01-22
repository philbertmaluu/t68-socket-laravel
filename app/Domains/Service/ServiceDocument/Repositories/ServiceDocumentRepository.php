<?php

namespace App\Domains\Service\ServiceDocument\Repositories;

use App\Domains\Service\ServiceDocument\Models\ServiceDocument;
use App\Shared\Helpers\PaginationHelper;
use Illuminate\Database\Eloquent\Collection;

class ServiceDocumentRepository
{
    public function findById(int|string $id, bool $withTrashed = false): ?ServiceDocument
    {
        $query = ServiceDocument::query();
        
        if ($withTrashed) {
            $query->withTrashed();
        }
        
        return $query->find($id);
    }

    public function findAll(array $filters = []): Collection
    {
        $query = ServiceDocument::query();

        if (isset($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        if (isset($filters['is_required'])) {
            $query->where('is_required', $filters['is_required']);
        }

        if (isset($filters['with_trashed']) && $filters['with_trashed']) {
            $query->withTrashed();
        } elseif (isset($filters['only_trashed']) && $filters['only_trashed']) {
            $query->onlyTrashed();
        }

        return $query->orderBy('order_index')->get();
    }

    public function create(array $data): ServiceDocument
    {
        return ServiceDocument::create($data);
    }

    public function update(ServiceDocument $serviceDocument, array $data): ServiceDocument
    {
        $serviceDocument->update($data);
        return $serviceDocument->fresh();
    }

    public function delete(ServiceDocument $serviceDocument, bool $force = false): bool
    {
        if ($force) {
            return $serviceDocument->forceDelete();
        }
        return $serviceDocument->delete();
    }

    public function restore(ServiceDocument $serviceDocument): bool
    {
        return $serviceDocument->restore();
    }

    public function paginate(int $perPage = 15, int $page = 1, array $filters = []): array
    {
        [$page, $perPage] = PaginationHelper::validateParams($page, $perPage);
        $query = ServiceDocument::query();

        if (isset($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        if (isset($filters['is_required'])) {
            $query->where('is_required', $filters['is_required']);
        }

        if (isset($filters['with_trashed']) && $filters['with_trashed']) {
            $query->withTrashed();
        } elseif (isset($filters['only_trashed']) && $filters['only_trashed']) {
            $query->onlyTrashed();
        }

        $total = $query->count();
        $items = $query->orderBy('order_index')->skip(($page - 1) * $perPage)->take($perPage)->get();
        $meta = PaginationHelper::calculateMeta($total, $perPage, $page);

        return [
            'data' => $items,
            'meta' => $meta,
        ];
    }
}
