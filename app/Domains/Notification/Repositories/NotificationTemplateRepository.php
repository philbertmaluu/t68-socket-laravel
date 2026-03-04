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
     * Tries exact locale first, then falls back to default locale (sw) if needed.
     */
    public function findActiveByKeyAndLocale(string $key, ?string $locale = null, ?string $channel = 'sms'): ?NotificationTemplate
    {
        $baseQuery = NotificationTemplate::query()
            ->where('key', $key)
            ->where('active', true);

        if ($channel !== null) {
            $baseQuery->where('channel', $channel);
        }

        // 1) Try exact locale match if provided
        if ($locale !== null) {
            $exact = (clone $baseQuery)->where('locale', $locale)->first();
            if ($exact !== null) {
                return $exact;
            }
        }

        // 2) Fallback to default locale (sw) if available
        $fallback = (clone $baseQuery)->where('locale', 'sw')->first();
        if ($fallback !== null) {
            return $fallback;
        }

        // 3) As a last resort, return any active template with this key/channel
        return $baseQuery->orderBy('locale')->first();
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

