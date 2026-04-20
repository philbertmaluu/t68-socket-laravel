<?php

namespace App\Domains\Counter\Repositories;

use App\Domains\Counter\Models\Counter;
use App\Domains\Counter\Models\CounterClerk;
use App\Domains\Authentication\Models\User;
use App\Shared\Helpers\PaginationHelper;
use Illuminate\Database\Eloquent\Collection;

class CounterRepository
{
    public function findById(int|string $id, bool $withTrashed = false): ?Counter
    {
        $query = Counter::query();
        
        if ($withTrashed) {
            $query->withTrashed();
        }
        
        return $query->find($id);
    }

    public function findAll(array $filters = []): Collection
    {
        $query = Counter::query();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['counter_type_id'])) {
            $query->where('counter_type_id', $filters['counter_type_id']);
        }

        if (isset($filters['service_id'])) {
            $query->whereHas('services', fn ($q) => $q->where('services.id', $filters['service_id']));
        }

        if (isset($filters['office_id'])) {
            $query->where('office_id', $filters['office_id']);
        }

        if (isset($filters['with_trashed']) && $filters['with_trashed']) {
            $query->withTrashed();
        } elseif (isset($filters['only_trashed']) && $filters['only_trashed']) {
            $query->onlyTrashed();
        }

        $items = $query->get();
        $this->appendActiveClerkToCounters($items);
        return $items;
    }

    public function create(array $data): Counter
    {
        $officeId = $data['office_id'] ?? '1';
        $serviceIds = $data['service_ids'] ?? [];
        $serviceIds = is_array($serviceIds) ? array_map('intval', array_values($serviceIds)) : [];

        $payload = $data;
        unset($payload['service_ids']);
        $counter = Counter::create($payload);

        if (count($serviceIds) > 0) {
            $sync = [];
            foreach ($serviceIds as $id) {
                $sync[$id] = ['office_id' => $officeId];
            }
            $counter->services()->sync($sync);
        }

        return $counter->load('services');
    }

    public function update(Counter $counter, array $data): Counter
    {
        $officeId = $data['office_id'] ?? $counter->office_id ?? '1';
        $serviceIds = $data['service_ids'] ?? null;
        if ($serviceIds !== null && is_array($serviceIds)) {
            $serviceIds = array_map('intval', array_values($serviceIds));
            $sync = [];
            foreach ($serviceIds as $id) {
                $sync[$id] = ['office_id' => $officeId];
            }
            $counter->services()->sync($sync);
        }
        unset($data['service_ids']);
        $counter->update($data);
        return $counter->fresh(['services']);
    }

    public function delete(Counter $counter, bool $force = false): bool
    {
        if ($force) {
            return $counter->forceDelete();
        }
        return $counter->delete();
    }

    public function restore(Counter $counter): bool
    {
        return $counter->restore();
    }

    public function paginate(int $perPage = 15, int $page = 1, array $filters = []): array
    {
        [$page, $perPage] = PaginationHelper::validateParams($page, $perPage);
        $query = Counter::query()->with(['counterType', 'services']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['counter_type_id'])) {
            $query->where('counter_type_id', $filters['counter_type_id']);
        }

        if (isset($filters['service_id'])) {
            $query->whereHas('services', fn ($q) => $q->where('services.id', $filters['service_id']));
        }

        if (isset($filters['office_id'])) {
            $query->where('office_id', $filters['office_id']);
        }

        if (isset($filters['with_trashed']) && $filters['with_trashed']) {
            $query->withTrashed();
        } elseif (isset($filters['only_trashed']) && $filters['only_trashed']) {
            $query->onlyTrashed();
        }

        $total = $query->count();
        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get();
        $this->appendActiveClerkToCounters($items);
        $meta = PaginationHelper::calculateMeta($total, $perPage, $page);

        return [
            'data' => $items,
            'meta' => $meta,
        ];
    }

    private function appendActiveClerkToCounters(Collection $counters): void
    {
        if ($counters->isEmpty()) {
            return;
        }

        $counterIds = $counters->pluck('id')->map(fn ($id) => (string) $id)->values()->all();

        $assignments = CounterClerk::query()
            ->whereIn('counter_id', $counterIds)
            ->where('is_active', true)
            ->orderByDesc('assigned_at')
            ->get()
            ->groupBy(fn ($a) => (string) $a->counter_id)
            ->map(fn ($group) => $group->first());

        $clerkIds = $assignments
            ->pluck('clerk_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $users = User::query()
            ->select(['id', 'user_id', 'name', 'email', 'user_type'])
            ->whereIn('id', $clerkIds)
            ->get()
            ->keyBy(fn ($u) => (string) $u->id);

        foreach ($counters as $counter) {
            $assignment = $assignments->get((string) $counter->id);
            if (!$assignment) {
                $counter->setAttribute('clerk', null);
                continue;
            }

            $user = $users->get((string) $assignment->clerk_id);
            $counter->setAttribute('clerk', [
                'id' => (string) $assignment->clerk_id,
                'pfno' => $user?->user_id,
                'name' => $user?->name,
                'email' => $user?->email,
                'department' => $user?->user_type,
                'assigned_at' => $assignment->assigned_at?->toIso8601String(),
            ]);
        }
    }
}
