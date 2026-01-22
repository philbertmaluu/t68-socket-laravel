<?php

namespace App\Domains\Ticket\Controllers;

use App\Domains\Ticket\Requests\StoreTicketRequest;
use App\Domains\Ticket\Requests\UpdateTicketRequest;
use App\Domains\Ticket\Services\TicketService;
use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends BaseController
{
    private TicketService $service;

    public function __construct()
    {
        $this->service = new TicketService();
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = (int) $request->get('per_page', 15);
            $page = (int) $request->get('page', 1);
            $filters = $request->only(['status', 'queue_id', 'service_id', 'counter_id', 'clerk_id', 'office_id', 'priority']);

            $result = $this->service->paginate($perPage, $page, $filters);

            return $this->sendResponse($result['data'], 'Tickets retrieved successfully', ['meta' => $result['meta']]);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve tickets', ['error' => $e->getMessage()], 500);
        }
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        try {
            $ticket = $this->service->createTicket($request->validated());
            return $this->sendResponse($ticket, 'Ticket created successfully', [], 201);
        } catch (\Exception $e) {
            return $this->sendError('Failed to create ticket', ['error' => $e->getMessage()], 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $ticket = $this->service->findById($id);

            if (!$ticket) {
                return $this->sendError('Ticket not found', [], 404);
            }

            return $this->sendResponse($ticket, 'Ticket retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve ticket', ['error' => $e->getMessage()], 500);
        }
    }

    public function update(UpdateTicketRequest $request, string $id): JsonResponse
    {
        try {
            $ticket = $this->service->findById($id);

            if (!$ticket) {
                return $this->sendError('Ticket not found', [], 404);
            }

            $updated = $this->service->updateTicket($ticket, $request->validated());
            return $this->sendResponse($updated, 'Ticket updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to update ticket', ['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $ticket = $this->service->findById($id);

            if (!$ticket) {
                return $this->sendError('Ticket not found', [], 404);
            }

            $this->service->deleteTicket($ticket);
            return $this->sendResponse(null, 'Ticket deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete ticket', ['error' => $e->getMessage()], 500);
        }
    }
}
