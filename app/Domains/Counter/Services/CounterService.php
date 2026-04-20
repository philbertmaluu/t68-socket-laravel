<?php

namespace App\Domains\Counter\Services;

use App\Domains\Counter\Models\Counter;
use App\Domains\Counter\Models\CounterClerk;
use App\Domains\Counter\Repositories\CounterRepository;
use App\Shared\Helpers\TransactionHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

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
            // Hardcode office_id for now
            $data['office_id'] = $data['office_id'] ?? '1';
            return $this->repository->create($data);
        });
    }

    public function updateCounter(Counter $counter, array $data): Counter
    {
        return TransactionHelper::execute(function () use ($counter, $data) {
            // Hardcode office_id for now if not provided
            if (!isset($data['office_id']) || empty($data['office_id'])) {
                $data['office_id'] = '1';
            }
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
            throw new \Exception('User not authenticated');
        }

        $candidateClerkIds = array_values(array_filter([
            isset($user->id) ? (string) $user->id : null,
            isset($user->pfno) ? (string) $user->pfno : null,
            isset($user->username) ? (string) $user->username : null,
            isset($user->email) ? (string) $user->email : null,
        ]));

        if (count($candidateClerkIds) === 0) {
            throw new \Exception('User identifier missing');
        }

        $assignment = CounterClerk::query()
            ->whereIn('clerk_id', $candidateClerkIds)
            ->where('is_active', true)
            ->latest('assigned_at')
            ->first();

        if (!$assignment) {
            throw new \Exception('User not assigned to any active counter');
        }

        $counter = Counter::query()
            ->with('counterType')
            ->find($assignment->counter_id);

        if (!$counter) {
            throw new \Exception('Assigned counter not found');
        }

        if (strtoupper((string) $counter->status) !== 'ACTIVE') {
            throw new \Exception('Your assigned counter is inactive. Please contact supervisor.');
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
}
