<?php

namespace App\Domains\CounterType\Services;

use App\Domains\CounterType\Models\CounterType;
use App\Domains\CounterType\Repositories\CounterTypeRepository;
use App\Shared\Helpers\TransactionHelper;
use Illuminate\Database\Eloquent\Collection;

class CounterTypeService
{
    private CounterTypeRepository $repository;

    public function __construct()
    {
        $this->repository = new CounterTypeRepository();
    }

    public function findById(int|string $id, bool $withTrashed = false): ?CounterType
    {
        return $this->repository->findById($id, $withTrashed);
    }

    public function findAll(array $filters = []): Collection
    {
        return $this->repository->findAll($filters);
    }

    public function findByCode(string $code): ?CounterType
    {
        return $this->repository->findByCode($code);
    }

    public function createCounterType(array $data): CounterType
    {
        $this->validateCounterTypeData($data);
        return TransactionHelper::execute(function () use ($data) {
            return $this->repository->create($data);
        });
    }

    public function updateCounterType(CounterType $counterType, array $data): CounterType
    {
        $this->validateCounterTypeData($data, $counterType);
        return TransactionHelper::execute(function () use ($counterType, $data) {
            return $this->repository->update($counterType, $data);
        });
    }

    public function deleteCounterType(CounterType $counterType, bool $force = false): bool
    {
        return TransactionHelper::execute(function () use ($counterType, $force) {
            return $this->repository->delete($counterType, $force);
        });
    }

    public function restoreCounterType(CounterType $counterType): bool
    {
        return TransactionHelper::execute(function () use ($counterType) {
            return $this->repository->restore($counterType);
        });
    }

    public function paginate(int $perPage = 15, int $page = 1, array $filters = []): array
    {
        return $this->repository->paginate($perPage, $page, $filters);
    }

    private function validateCounterTypeData(array $data, ?CounterType $counterType = null): void
    {
        if (isset($data['code'])) {
            $existing = $this->repository->findByCode($data['code']);
            if ($existing && (!$counterType || $existing->id !== $counterType->id)) {
                throw new \InvalidArgumentException('Code already exists');
            }
        }
    }
}
