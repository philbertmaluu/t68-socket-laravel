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
        return $this->repository->findById($id, $withTrashed);
    }

    public function findAll(array $filters = []): Collection
    {
        return $this->repository->findAll($this->scopeFiltersByHrpOffice($filters));
    }

    public function createCounter(array $data): Counter
    {
        return TransactionHelper::execute(function () use ($data) {
            $clerkId = $data['clerk_id'] ?? null;
            unset($data['clerk_id']);
            $data = $this->fillOfficeRegionFromHrp($data);
            $counter = $this->repository->create($data);
            $this->syncCounterClerkAssignment($counter, $clerkId);
            return $this->attachClerkPayload($counter->fresh(['services']));
        });
    }

    public function updateCounter(Counter $counter, array $data): Counter
    {
        return TransactionHelper::execute(function () use ($counter, $data) {
            $clerkPayloadProvided = array_key_exists('clerk_id', $data);
            $clerkId = $data['clerk_id'] ?? null;
            unset($data['clerk_id']);
            $data = $this->fillOfficeRegionFromHrp($data);
            $updatedCounter = $this->repository->update($counter, $data);
            if ($clerkPayloadProvided) {
                $this->syncCounterClerkAssignment($updatedCounter, $clerkId);
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
     *
     * @return array{
     *   id: string|int,
     *   name: string,
     *   office_id: string|int|null,
     *   status: string|null,
     *   counter_type: array{id: string|int|null, name: string|null, code: string|null},
     *   clerk: array{id: string|int|null, pfno: string|null, name: string|null}
     * }
     */
    public function getCurrentUserCounter(): array
    {
        $user = Auth::user();
        if (!$user) {
            throw new AuthenticationException('User not authenticated');
        }

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
            ->with('counterType')
            ->find($assignment->counter_id);

        if (!$counter) {
            throw new NotFoundHttpException('Assigned counter not found');
        }

        if (strtoupper((string) $counter->status) !== 'ACTIVE') {
            throw new UnprocessableEntityHttpException('Your assigned counter is inactive. Please contact supervisor.');
        }

        return [
            'id' => $counter->id,
            'name' => $counter->name,
            'office_id' => $counter->office_id,
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
        ];
    }

    private function syncCounterClerkAssignment(Counter $counter, ?string $clerkId): void
    {
        $normalizedClerkId = trim((string) $clerkId);

        if ($normalizedClerkId === '') {
            CounterClerk::query()
                ->where('counter_id', (string) $counter->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'unassigned_at' => now(),
                ]);
            return;
        }

        // A clerk should only have one active counter assignment.
        CounterClerk::query()
            ->where('clerk_id', $normalizedClerkId)
            ->where('counter_id', '!=', (string) $counter->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'unassigned_at' => now(),
            ]);

        // Counter should have one active clerk assignment.
        CounterClerk::query()
            ->where('counter_id', (string) $counter->id)
            ->where('clerk_id', '!=', $normalizedClerkId)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'unassigned_at' => now(),
            ]);

        $assignment = CounterClerk::query()
            ->where('counter_id', (string) $counter->id)
            ->where('clerk_id', $normalizedClerkId)
            ->first();

        if ($assignment) {
            $assignment->update([
                'is_active' => true,
                'assigned_at' => now(),
                'unassigned_at' => null,
            ]);
            return;
        }

        CounterClerk::query()->create([
            'counter_id' => (string) $counter->id,
            'clerk_id' => $normalizedClerkId,
            'is_active' => true,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    private function attachClerkPayload(Counter $counter): Counter
    {
        $assignment = CounterClerk::query()
            ->where('counter_id', (string) $counter->id)
            ->where('is_active', true)
            ->latest('assigned_at')
            ->first();

        if (!$assignment) {
            $counter->setAttribute('clerk', null);
            return $counter;
        }

        $user = User::query()
            ->select(['id', 'user_id', 'name', 'email', 'user_type'])
            ->find($assignment->clerk_id);

        $counter->setAttribute('clerk', [
            'id' => (string) $assignment->clerk_id,
            'pfno' => $user?->user_id,
            'name' => $user?->name,
            'email' => $user?->email,
            'department' => $user?->user_type,
            'assigned_at' => $assignment->assigned_at?->toIso8601String(),
        ]);

        return $counter;
    }
}
