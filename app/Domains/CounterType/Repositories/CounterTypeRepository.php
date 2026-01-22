<?php

namespace App\Domains\CounterType\Repositories;

use App\Domains\CounterType\Models\CounterType;
use App\Shared\Helpers\PaginationHelper;
use Illuminate\Database\Eloquent\Collection;

class CounterTypeRepository
{
    public function findById(int|string $id, bool $withTrashed = false): ?CounterType
    {
        $query = CounterType::query();
        
        if ($withTrashed) {
            $query->withTrashed();
        }
        
        return $query->find($id);
    }

    public function findAll(array $filters = []): Collection
    {
        $query = CounterType::query();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['code'])) {
            $query->where('code', $filters['code']);
        }

        if (isset($filters['with_trashed']) && $filters['with_trashed']) {
            $query->withTrashed();
        } elseif (isset($filters['only_trashed']) && $filters['only_trashed']) {
            $query->onlyTrashed();
        }

        return $query->get();
    }

    public function findByCode(string $code): ?CounterType
    {
        return CounterType::where('code', $code)->first();
    }

    public function create(array $data): CounterType
    {
        return CounterType::create($data);
    }

    public function update(CounterType $counterType, array $data): CounterType
    {
        $counterType->update($data);
        return $counterType->fresh();
    }

    public function delete(CounterType $counterType, bool $force = false): bool
    {
        if ($force) {
            return $counterType->forceDelete();
        }
        return $counterType->delete();
    }

    public function restore(CounterType $counterType): bool
    {
        return $counterType->restore();
    }

    public function paginate(int $perPage = 15, int $page = 1, array $filters = []): array
    {
        [$page, $perPage] = PaginationHelper::validateParams($page, $perPage);
        $query = CounterType::query();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['code'])) {
            $query->where('code', $filters['code']);
        }

        if (isset($filters['with_trashed']) && $filters['with_trashed']) {
            $query->withTrashed();
        } elseif (isset($filters['only_trashed']) && $filters['only_trashed']) {
            $query->onlyTrashed();
        }

        $total = $query->count();
        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get();
        $meta = PaginationHelper::calculateMeta($total, $perPage, $page);

        return [
            'data' => $items,
            'meta' => $meta,
        ];
    }
}
