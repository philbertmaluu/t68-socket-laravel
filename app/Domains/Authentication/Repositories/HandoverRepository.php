<?php

namespace App\Domains\Authentication\Repositories;

use App\Domains\Authentication\Models\Handover;
use Illuminate\Database\Eloquent\Collection;

class HandoverRepository
{
    public function create(array $data): Handover
    {
        return Handover::create($data);
    }

    public function findById(int $id): ?Handover
    {
        return Handover::find($id);
    }

    public function findActiveByUser(string $userId): Collection
    {
        return Handover::where(function ($query) use ($userId) {
            $query->where('from_user_id', $userId)
                  ->orWhere('to_user_id', $userId);
        })
        ->where('status', 'active')
        ->with(['fromUser', 'toUser', 'role', 'userRole'])
        ->orderBy('handover_date', 'desc')
        ->get();
    }

    public function findHistoryByUser(string $userId): Collection
    {
        return Handover::where(function ($query) use ($userId) {
            $query->where('from_user_id', $userId)
                  ->orWhere('to_user_id', $userId);
        })
        ->with(['fromUser', 'toUser', 'role', 'userRole'])
        ->orderBy('handover_date', 'desc')
        ->get();
    }

    public function update(Handover $handover, array $data): Handover
    {
        $handover->update($data);
        return $handover->fresh();
    }
}
