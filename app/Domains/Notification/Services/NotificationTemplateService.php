<?php

namespace App\Domains\Notification\Services;

use App\Domains\Notification\Models\NotificationTemplate;
use App\Domains\Notification\Repositories\NotificationTemplateRepository;
use Illuminate\Database\Eloquent\Collection;

class NotificationTemplateService
{
    private NotificationTemplateRepository $repository;

    public function __construct()
    {
        $this->repository = new NotificationTemplateRepository();
    }

    public function findById(int|string $id, bool $withTrashed = false): ?NotificationTemplate
    {
        return $this->repository->findById($id, $withTrashed);
    }

    public function findAll(array $filters = []): Collection
    {
        return $this->repository->findAll($filters);
    }

    public function paginate(int $perPage = 15, int $page = 1, array $filters = []): array
    {
        return $this->repository->paginate($perPage, $page, $filters);
    }

    public function create(array $data): NotificationTemplate
    {
        return $this->repository->create($data);
    }

    public function update(NotificationTemplate $template, array $data): NotificationTemplate
    {
        return $this->repository->update($template, $data);
    }

    public function delete(NotificationTemplate $template, bool $force = false): bool
    {
        return $this->repository->delete($template, $force);
    }

    public function findActiveByKeyAndLocale(
        string $key,
        ?string $locale = null,
        ?string $channel = 'sms',
        int|string|null $tenantId = null
    ): ?NotificationTemplate {
        return $this->repository->findActiveByKeyAndLocale($key, $locale, $channel, $tenantId);
    }
}

