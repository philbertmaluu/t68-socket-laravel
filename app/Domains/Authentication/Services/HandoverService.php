<?php

namespace App\Domains\Authentication\Services;

use App\Domains\Authentication\Models\Handover;
use App\Domains\Authentication\Models\UserRole;
use App\Domains\Authentication\Repositories\HandoverRepository;
use App\Shared\Helpers\TransactionHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class HandoverService
{
    private HandoverRepository $repository;

    public function __construct()
    {
        $this->repository = new HandoverRepository();
    }

    public function initiateHandover(string $fromUserId, array $roleIds, string $toUserId, ?string $notes = null): array
    {
        $userRoles = UserRole::where('user_id', $fromUserId)
            ->whereIn('role_id', $roleIds)
            ->where('status', 'active')
            ->get();

        if ($userRoles->isEmpty()) {
            throw new \Exception('No active roles found for user.');
        }

        if ($userRoles->count() !== count($roleIds)) {
            throw new \Exception('Some roles are not active for this user.');
        }

        return TransactionHelper::execute(function () use ($userRoles, $fromUserId, $toUserId, $notes) {
            $handovers = [];

            foreach ($userRoles as $userRole) {
                $userRole->update([
                    'status' => 'handover',
                    'handover_to_user_id' => $toUserId,
                    'handover_date' => now(),
                ]);

                $handovers[] = $this->repository->create([
                    'user_role_id' => $userRole->id,
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $toUserId,
                    'role_id' => $userRole->role_id,
                    'handover_date' => now(),
                    'status' => 'active',
                    'notes' => $notes,
                    'created_by' => Auth::user()?->id ?? null,
                ]);
            }

            return $handovers;
        });
    }

    public function completeHandover(int $handoverId): bool
    {
        $handover = $this->repository->findById($handoverId);

        if (!$handover || $handover->status !== 'active') {
            throw new \Exception('Invalid handover or handover already processed.');
        }

        return TransactionHelper::execute(function () use ($handover) {
            $userRole = $handover->userRole;

            $userRole->update([
                'status' => 'inactive',
                'end_date' => now(),
            ]);

            UserRole::create([
                'user_id' => $handover->to_user_id,
                'role_id' => $handover->role_id,
                'start_date' => now(),
                'status' => 'active',
                'created_by' => Auth::user()?->id ?? null,
            ]);

            $this->repository->update($handover, [
                'status' => 'completed',
            ]);

            return true;
        });
    }

    public function cancelHandover(int $handoverId): bool
    {
        $handover = $this->repository->findById($handoverId);

        if (!$handover || $handover->status !== 'active') {
            throw new \Exception('Invalid handover or handover already processed.');
        }

        return TransactionHelper::execute(function () use ($handover) {
            $userRole = $handover->userRole;

            $userRole->update([
                'status' => 'active',
                'handover_to_user_id' => null,
                'handover_date' => null,
            ]);

            $this->repository->update($handover, [
                'status' => 'cancelled',
            ]);

            return true;
        });
    }

    public function getActiveHandovers(string $userId): Collection
    {
        return $this->repository->findActiveByUser($userId);
    }

    public function getHandoverHistory(string $userId): Collection
    {
        return $this->repository->findHistoryByUser($userId);
    }
}
