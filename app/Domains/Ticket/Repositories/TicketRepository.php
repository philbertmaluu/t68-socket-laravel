<?php

namespace App\Domains\Ticket\Repositories;

use App\Domains\Ticket\Models\Ticket;
use App\Shared\Helpers\PaginationHelper;
use Illuminate\Database\Eloquent\Collection;

class TicketRepository
{
    public function findById(int|string $id, bool $withTrashed = false): ?Ticket
    {
        $query = Ticket::query();
        
        if ($withTrashed) {
            $query->withTrashed();
        }
        
        return $query->find($id);
    }

    public function findAll(array $filters = []): Collection
    {
        $query = Ticket::query();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['queue_id'])) {
            $query->where('queue_id', $filters['queue_id']);
        }

        if (isset($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        if (isset($filters['counter_id'])) {
            $query->where('counter_id', $filters['counter_id']);
        }

        if (isset($filters['clerk_id'])) {
            $query->where('clerk_id', $filters['clerk_id']);
        }

        if (isset($filters['office_id'])) {
            $query->where('office_id', $filters['office_id']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['with_trashed']) && $filters['with_trashed']) {
            $query->withTrashed();
        } elseif (isset($filters['only_trashed']) && $filters['only_trashed']) {
            $query->onlyTrashed();
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function findByTicketNumber(string $ticketNumber, ?string $tenantId = null): ?Ticket
    {
        $query = Ticket::query()->where('ticket_number', $ticketNumber);
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        
        return $query->first();
    }

    public function create(array $data): Ticket
    {
        return Ticket::create($data);
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        $ticket->update($data);
        return $ticket->fresh();
    }

    public function delete(Ticket $ticket, bool $force = false): bool
    {
        if ($force) {
            return $ticket->forceDelete();
        }
        return $ticket->delete();
    }

    public function restore(Ticket $ticket): bool
    {
        return $ticket->restore();
    }

    public function paginate(int $perPage = 15, int $page = 1, array $filters = []): array
    {
        [$page, $perPage] = PaginationHelper::validateParams($page, $perPage);
        $query = Ticket::query();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['queue_id'])) {
            $query->where('queue_id', $filters['queue_id']);
        }

        if (isset($filters['service_id'])) {
            $query->where('service_id', $filters['service_id']);
        }

        if (isset($filters['counter_id'])) {
            $query->where('counter_id', $filters['counter_id']);
        }

        if (isset($filters['clerk_id'])) {
            $query->where('clerk_id', $filters['clerk_id']);
        }

        if (isset($filters['office_id'])) {
            $query->where('office_id', $filters['office_id']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['with_trashed']) && $filters['with_trashed']) {
            $query->withTrashed();
        } elseif (isset($filters['only_trashed']) && $filters['only_trashed']) {
            $query->onlyTrashed();
        }

        $total = $query->count();
        $items = $query->orderBy('created_at', 'desc')->skip(($page - 1) * $perPage)->take($perPage)->get();
        $meta = PaginationHelper::calculateMeta($total, $perPage, $page);

        return [
            'data' => $items,
            'meta' => $meta,
        ];
    }
}
