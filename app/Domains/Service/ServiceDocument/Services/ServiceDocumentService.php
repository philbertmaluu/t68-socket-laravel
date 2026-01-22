<?php

namespace App\Domains\Service\ServiceDocument\Services;

use App\Domains\Service\ServiceDocument\Models\ServiceDocument;
use App\Domains\Service\ServiceDocument\Repositories\ServiceDocumentRepository;
use App\Shared\Helpers\TransactionHelper;
use Illuminate\Database\Eloquent\Collection;

class ServiceDocumentService
{
    private ServiceDocumentRepository $repository;

    public function __construct()
    {
        $this->repository = new ServiceDocumentRepository();
    }

    public function findById(int|string $id, bool $withTrashed = false): ?ServiceDocument
    {
        return $this->repository->findById($id, $withTrashed);
    }

    public function findAll(array $filters = []): Collection
    {
        return $this->repository->findAll($filters);
    }

    public function createServiceDocument(array $data): ServiceDocument
    {
        return TransactionHelper::execute(function () use ($data) {
            return $this->repository->create($data);
        });
    }

    public function updateServiceDocument(ServiceDocument $serviceDocument, array $data): ServiceDocument
    {
        return TransactionHelper::execute(function () use ($serviceDocument, $data) {
            return $this->repository->update($serviceDocument, $data);
        });
    }

    public function deleteServiceDocument(ServiceDocument $serviceDocument, bool $force = false): bool
    {
        return TransactionHelper::execute(function () use ($serviceDocument, $force) {
            return $this->repository->delete($serviceDocument, $force);
        });
    }

    public function restoreServiceDocument(ServiceDocument $serviceDocument): bool
    {
        return TransactionHelper::execute(function () use ($serviceDocument) {
            return $this->repository->restore($serviceDocument);
        });
    }

    public function paginate(int $perPage = 15, int $page = 1, array $filters = []): array
    {
        return $this->repository->paginate($perPage, $page, $filters);
    }
}
