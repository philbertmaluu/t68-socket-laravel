<?php

namespace App\Domains\Audit\Services;

use App\Domains\Audit\Models\AuditTrail;
use App\Domains\Audit\Repositories\AuditTrailRepository;
use Illuminate\Database\Eloquent\Collection;

class AuditTrailService
{
    private AuditTrailRepository $repository;

    public function __construct()
    {
        $this->repository = new AuditTrailRepository();
    }

    public function findById(int|string $id): ?AuditTrail
    {
        return $this->repository->findById($id);
    }

    public function findAll(array $filters = []): Collection
    {
        return $this->repository->findAll($filters);
    }

    public function paginate(int $perPage = 15, int $page = 1, array $filters = []): array
    {
        return $this->repository->paginate($perPage, $page, $filters);
    }
}
