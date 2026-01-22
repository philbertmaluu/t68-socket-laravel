<?php

namespace App\Domains\Counter\Services;

use App\Domains\Counter\Models\Counter;
use App\Domains\Counter\Repositories\CounterRepository;
use App\Shared\Helpers\TransactionHelper;
use Illuminate\Database\Eloquent\Collection;

class CounterService
{
    private CounterRepository $repository;

    public function __construct()
    {
        $this->repository = new CounterRepository();
    }

    public function findById(int|string $id, bool $withTrashed = false): ?Counter
    {
        return $this->repository->findById($id, $withTrashed);
    }

    public function findAll(array $filters = []): Collection
    {
        return $this->repository->findAll($filters);
    }

    public function createCounter(array $data): Counter
    {
        return TransactionHelper::execute(function () use ($data) {
            return $this->repository->create($data);
        });
    }

    public function updateCounter(Counter $counter, array $data): Counter
    {
        return TransactionHelper::execute(function () use ($counter, $data) {
            return $this->repository->update($counter, $data);
        });
    }

    public function deleteCounter(Counter $counter, bool $force = false): bool
    {
        return TransactionHelper::execute(function () use ($counter, $force) {
            return $this->repository->delete($counter, $force);
        });
    }

    public function restoreCounter(Counter $counter): bool
    {
        return TransactionHelper::execute(function () use ($counter) {
            return $this->repository->restore($counter);
        });
    }

    public function paginate(int $perPage = 15, int $page = 1, array $filters = []): array
    {
        return $this->repository->paginate($perPage, $page, $filters);
    }
}
