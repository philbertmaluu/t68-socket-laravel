<?php

namespace App\Domains\Notification\Repositories;

use App\Domains\Notification\Models\NotificationTemplate;
use App\Shared\Helpers\PaginationHelper;
use Illuminate\Database\Eloquent\Collection;

class NotificationTemplateRepository
{
    public function findById(int|string $id, bool $withTrashed = false): ?NotificationTemplate
    {
        $query = NotificationTemplate::query();

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->find($id);
    }

    public function findAll(array $filters = []): Collection
    {
        $query = NotificationTemplate::query();

        if (isset($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (isset($filters['locale'])) {
            $query->where('locale', $filters['locale']);
        }

        if (isset($filters['active'])) {
            $query->where('active', (bool) $filters['active']);
        }

        if (isset($filters['key'])) {
            $query->where('key', 'like', '%' . $filters['key'] . '%');
        }

        return $query->orderBy('key')->orderBy('locale')->get();
    }

    public function paginate(int $perPage = 15, int $page = 1, array $filters = []): array
    {
        [$page, $perPage] = PaginationHelper::validateParams($page, $perPage);

        $query = NotificationTemplate::query();

        if (isset($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (isset($filters['locale'])) {
            $query->where('locale', $filters['locale']);
        }

        if (isset($filters['active'])) {
            $query->where('active', (bool) $filters['active']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', '%' . $search . '%')
                    ->orWhere('body', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $total = $query->count();
        $items = $query
            ->orderBy('key')
            ->orderBy('locale')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $meta = PaginationHelper::calculateMeta($total, $perPage, $page);

        return [
            'data' => $items,
            'meta' => $meta,
        ];
    }

    /**
     * Find the best active template for a given key/channel/locale.
     * Ignores Auth tenant scope — SMS is sent from ticket context, not admin session.
     * Prefers ticket-tenant rows over global (null), exact locale over sw fallback.
     */
    public function findActiveByKeyAndLocale(
        string $key,
        ?string $locale = null,
        ?string $channel = 'sms',
        int|string|null $tenantId = null
    ): ?NotificationTemplate {
        $baseQuery = NotificationTemplate::withoutGlobalScope('tenant')
            ->where('key', $key)
            ->where('active', true)
            ->where(function ($query) use ($tenantId) {
                $query->whereNull('tenant_id');
                if ($tenantId !== null && $tenantId !== '' && (int) $tenantId !== 0) {
                    $query->orWhere('tenant_id', $tenantId);
                }
            })
            // Tenant-specific rows first, then global defaults (Oracle-safe).
            ->orderByRaw('CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('locale');

        if ($channel !== null) {
            $baseQuery->where('channel', $channel);
        }

        if ($locale !== null) {
            $exact = (clone $baseQuery)->where('locale', $locale)->first();
            if ($exact !== null) {
                return $exact;
            }
        }

        $fallback = (clone $baseQuery)->where('locale', 'sw')->first();
        if ($fallback !== null) {
            return $fallback;
        }

        return $baseQuery->first();
    }

    public function create(array $data): NotificationTemplate
    {
        return NotificationTemplate::create($data);
    }

    public function update(NotificationTemplate $template, array $data): NotificationTemplate
    {
        $template->update($data);
        return $template->fresh();
    }

    public function delete(NotificationTemplate $template, bool $force = false): bool
    {
        if ($force) {
            return $template->forceDelete();
        }

        return $template->delete();
    }
}

