<?php

namespace App\Domains\Ticket\Services;

use App\Domains\Ticket\Models\Ticket;
use App\Domains\Ticket\Repositories\TicketRepository;
use App\Shared\Helpers\TransactionHelper;
use Illuminate\Database\Eloquent\Collection;

class TicketService
{
    private TicketRepository $repository;

    public function __construct()
    {
        $this->repository = new TicketRepository();
    }

    public function findById(int|string $id, bool $withTrashed = false): ?Ticket
    {
        return $this->repository->findById($id, $withTrashed);
    }

    public function findAll(array $filters = []): Collection
    {
        return $this->repository->findAll($filters);
    }

    public function findByTicketNumber(string $ticketNumber, ?string $tenantId = null): ?Ticket
    {
        return $this->repository->findByTicketNumber($ticketNumber, $tenantId);
    }

    public function createTicket(array $data): Ticket
    {
        return TransactionHelper::execute(function () use ($data) {
            return $this->repository->create($data);
        });
    }

    public function updateTicket(Ticket $ticket, array $data): Ticket
    {
        return TransactionHelper::execute(function () use ($ticket, $data) {
            return $this->repository->update($ticket, $data);
        });
    }

    public function deleteTicket(Ticket $ticket, bool $force = false): bool
    {
        return TransactionHelper::execute(function () use ($ticket, $force) {
            return $this->repository->delete($ticket, $force);
        });
    }

    public function restoreTicket(Ticket $ticket): bool
    {
        return TransactionHelper::execute(function () use ($ticket) {
            return $this->repository->restore($ticket);
        });
    }

    public function paginate(int $perPage = 15, int $page = 1, array $filters = []): array
    {
        return $this->repository->paginate($perPage, $page, $filters);
    }
}
