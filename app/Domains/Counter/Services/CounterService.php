<?php

namespace App\Domains\Counter\Services;

use App\Domains\Counter\Models\Counter;
use App\Domains\Counter\Models\CounterClerk;
use App\Domains\Authentication\Models\User;
use App\Domains\Counter\Repositories\CounterRepository;
use App\Shared\Helpers\TransactionHelper;
use App\Traits\UserOfficeTrait;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CounterService
{
    use UserOfficeTrait;

    private CounterRepository $repository;

    public function __construct()
    {
        $this->repository = new CounterRepository();
    }

    public function findById(int|string $id, bool $withTrashed = false): ?Counter
    {
        $counter = $this->repository->findById($id, $withTrashed);

        return $counter ? $this->attachClerkPayload($counter) : null;
    }

    public function findAll(array $filters = []): Collection
    {
        return $this->repository->findAll($this->scopeFiltersByHrpOffice($filters));
    }

    public function createCounter(array $data): Counter
    {
        return TransactionHelper::execute(function () use ($data) {
            $clerkIds = $this->extractClerkIds($data);
            $data = $this->fillOfficeRegionFromHrp($data);
            $counter = $this->repository->create($data);
            $this->syncCounterClerkAssignments($counter, $clerkIds ?? []);
            return $this->attachClerkPayload($counter->fresh(['services']));
        });
    }

    public function updateCounter(Counter $counter, array $data): Counter
    {
        return TransactionHelper::execute(function () use ($counter, $data) {
            $clerkIds = $this->extractClerkIds($data);
            $data = $this->fillOfficeRegionFromHrp($data);
            $updatedCounter = $this->repository->update($counter, $data);
            if ($clerkIds !== null) {
                $this->syncCounterClerkAssignments($updatedCounter, $clerkIds);
            }
            return $this->attachClerkPayload($updatedCounter->fresh(['services']));
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
        $result = $this->repository->paginate($perPage, $page, $this->scopeFiltersByHrpOffice($filters));
        $result['data'] = $this->withHrpOfficeNames($result['data']);

        return $result;
    }

    /**
     * List users that can be assigned as clerks.
     *
     * @return array<int, array{id: string, name: string, email: string, department: string}>
     */
    public function getClerks(?string $search = null, int $limit = 100): array
    {
        $limit = max(10, min(300, $limit));
        $search = trim((string) $search);

        $query = User::query()
            ->select(['id', 'user_id', 'name', 'email', 'user_type', 'is_active'])
            ->where('is_active', true);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('user_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->limit($limit)->get();

        return $users->map(function (User $user) {
            return [
                'id' => (string) ($user->id ?? ''),
                'name' => (string) ($user->name ?? ''),
                'email' => (string) ($user->email ?? ''),
                'department' => (string) ($user->user_type ?? 'General'),
            ];
        })->values()->all();
    }

    /**
     * Resolve current authenticated user's assigned counter (active assignment only).
     *
     * Matches `counter_clerk.clerk_id` by common user identifiers to support mixed datasets.
     * Scoped to the authenticated user's HRPD office.
     *
     * @return array{
     *   id: string|int,
     *   name: string,
     *   office_id: string|int|null,
     *   office_name: string|null,
     *   status: string|null,
     *   counter_type: array{id: string|int|null, name: string|null, code: string|null},
     *   clerk: array{id: string|int|null, pfno: string|null, name: string|null},
     *   services: list<array{id: string|int, name: string, swahili_name: string|null, status: string|null}>
     * }
     */
    public function getCurrentUserCounter(): array
    {
        $user = Auth::user();
        if (!$user) {
            throw new AuthenticationException('User not authenticated');
        }

        $location = $this->getUserOfficeAndRegionFromHrp();
        $officeId = (string) $location['office_id'];
        $officeName = $location['office_name'] ?? null;

        $candidateClerkIds = array_values(array_filter([
            isset($user->id) ? (string) $user->id : null,
            isset($user->pfno) ? (string) $user->pfno : null,
            isset($user->username) ? (string) $user->username : null,
            isset($user->email) ? (string) $user->email : null,
        ]));

        if (count($candidateClerkIds) === 0) {
            throw new UnprocessableEntityHttpException('User identifier missing');
        }

        $assignment = CounterClerk::query()
            ->whereIn('clerk_id', $candidateClerkIds)
            ->where('is_active', true)
            ->latest('assigned_at')
            ->first();

        if (!$assignment) {
            throw new NotFoundHttpException('User not assigned to any active counter');
        }

        $counter = Counter::query()
            ->with(['counterType', 'services:id,name,swahili_name,status'])
            ->where('office_id', $officeId)
            ->find($assignment->counter_id);

        if (!$counter) {
            throw new NotFoundHttpException('You have not been assigned to any counter');
        }

        if (strtoupper((string) $counter->status) !== 'ACTIVE') {
            throw new UnprocessableEntityHttpException('Your assigned counter is inactive. Please contact supervisor.');
        }

        return [
            'id' => $counter->id,
            'name' => $counter->name,
            'office_id' => $counter->office_id,
            'office_name' => $officeName,
            'status' => $counter->status,
            'counter_type' => [
                'id' => $counter->counterType?->id,
                'name' => $counter->counterType?->name,
                'code' => $counter->counterType?->code,
            ],
            'clerk' => [
                'id' => $user->id ?? null,
                'pfno' => $user->pfno ?? null,
                'name' => $user->name ?? null,
            ],
            'services' => $counter->services
                ->map(static function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => (string) ($service->name ?? ''),
                        'swahili_name' => $service->swahili_name ? (string) $service->swahili_name : null,
                        'status' => $service->status ? (string) $service->status : null,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<string>|null Null when the request did not include clerk assignment fields.
     */
    private function extractClerkIds(array &$data): ?array
    {
        $provided = array_key_exists('clerk_ids', $data) || array_key_exists('clerk_id', $data);

        if (array_key_exists('clerk_ids', $data)) {
            $clerkIds = is_array($data['clerk_ids']) ? $data['clerk_ids'] : [];
        } elseif (array_key_exists('clerk_id', $data)) {
            $clerkIds = $data['clerk_id'] ? [$data['clerk_id']] : [];
        } else {
            $clerkIds = [];
        }

        unset($data['clerk_id'], $data['clerk_ids']);

        return $provided ? $this->normalizeClerkIds($clerkIds) : null;
    }

    /**
     * @param list<mixed> $clerkIds
     * @return list<string>
     */
    private function normalizeClerkIds(array $clerkIds): array
    {
        $normalized = [];
        foreach ($clerkIds as $clerkId) {
            $id = trim((string) $clerkId);
            if ($id === '') {
                continue;
            }
            $normalized[$id] = $id;
        }

        return array_values($normalized);
    }

    /**
     * @param list<string> $clerkIds
     */
    private function syncCounterClerkAssignments(Counter $counter, array $clerkIds): void
    {
        $counterId = (string) $counter->id;

        CounterClerk::query()
            ->where('counter_id', $counterId)
            ->where('is_active', true)
            ->when(
                count($clerkIds) > 0,
                fn ($query) => $query->whereNotIn('clerk_id', $clerkIds)
            )
            ->update([
                'is_active' => false,
                'unassigned_at' => now(),
            ]);

        foreach ($clerkIds as $clerkId) {
            CounterClerk::query()
                ->where('clerk_id', $clerkId)
                ->where('counter_id', '!=', $counterId)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'unassigned_at' => now(),
                ]);

            $assignment = CounterClerk::query()
                ->where('counter_id', $counterId)
                ->where('clerk_id', $clerkId)
                ->first();

            if ($assignment) {
                $assignment->update([
                    'is_active' => true,
                    'assigned_at' => now(),
                    'unassigned_at' => null,
                ]);
                continue;
            }

            CounterClerk::query()->create([
                'counter_id' => $counterId,
                'clerk_id' => $clerkId,
                'is_active' => true,
                'assigned_at' => now(),
                'unassigned_at' => null,
            ]);
        }
    }

    private function attachClerkPayload(Counter $counter): Counter
    {
        $assignments = CounterClerk::query()
            ->where('counter_id', (string) $counter->id)
            ->where('is_active', true)
            ->orderBy('assigned_at')
            ->get();

        $clerkIds = $assignments->pluck('clerk_id')->filter()->unique()->values()->all();
        $users = empty($clerkIds)
            ? collect()
            : User::query()
                ->select(['id', 'user_id', 'name', 'email', 'user_type'])
                ->whereIn('id', $clerkIds)
                ->get()
                ->keyBy(fn (User $user) => (string) $user->id);

        $clerks = $assignments->map(function (CounterClerk $assignment) use ($users) {
            $user = $users->get((string) $assignment->clerk_id);

            return [
                'id' => (string) $assignment->clerk_id,
                'pfno' => $user?->user_id,
                'name' => $user?->name,
                'email' => $user?->email,
                'department' => $user?->user_type,
                'assigned_at' => $assignment->assigned_at?->toIso8601String(),
            ];
        })->values()->all();

        return $counter->withClerkPayload($clerks[0] ?? null, $clerks);
    }
}
