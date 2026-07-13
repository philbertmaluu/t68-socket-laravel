<?php

namespace App\Domains\Ticket\Controllers;

use App\Domains\Ticket\Requests\StoreTicketRequest;
use App\Domains\Ticket\Requests\UpdateTicketRequest;
use App\Domains\Ticket\Services\TicketService;
use App\Http\Controllers\BaseController;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class TicketController extends BaseController
{
    private TicketService $service;

    public function __construct()
    {
        $this->service = new TicketService();
    }

    /**
     * List tickets with pagination and optional filters.
     *
     * Supported query params:
     * - per_page, page
     * - status, queue_id, service_id, counter_id, clerk_id, office_id, priority
     *
     * @param Request $request
     * @return JsonResponse
     */
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


    /**
     * Get queue tickets scoped to the authenticated clerk context.
     *
     * Uses current clerk assignment and request filters to return:
     * - tickets list
     * - summary statistics for cards/charts
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getClerksTickets(Request $request): JsonResponse
    {
        try {
            $clerks = $this->service->getClerksTickets($request->all());
            return $this->sendResponse($clerks, 'Clerks tickets retrieved successfully');
        } catch (AuthenticationException $e) {
            return $this->sendError($e->getMessage(), [], 401);
        } catch (NotFoundHttpException $e) {
            return $this->sendError($e->getMessage(), [], 404);
        } catch (UnprocessableEntityHttpException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve clerks tickets', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Get waiting and serving tickets grouped by office.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getWaitingAndServingTicketsPerOffice(Request $request): JsonResponse
    {
        try {
            $device = $request->attributes->get('device') ?? $request->user();
            $deviceId = is_object($device) && isset($device->id) ? (string) $device->id : null;

            if (!$deviceId) {
                return $this->sendError('Authenticated device not found', [], 401);
            }

            $tickets = $this->service->getWaitingAndServingTicketsPerOffice([
                'device_id' => $deviceId,
            ]);
            return $this->sendResponse($tickets, 'Waiting and serving tickets retrieved successfully');
        } catch (UnprocessableEntityHttpException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve waiting and serving tickets', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created ticket.
     * 
     * Required payload:
     * - service_type_id: Service ID (must exist in services table)
     * - phone_number: Customer phone number (for SMS notifications)
     * - office_id: Office ID
     * 
     * Automatically generated:
     * - ticket_number: Auto-generated (e.g. A1–Z500, then AA1–ZZ500, then AAA1…; digits 1–500 per letter block)
     * - queue_id: Found or created based on service_type_id and office_id
     * - service_type: Retrieved from service name
     * - service_id: Set from service_type_id
     * - estimated_time: Retrieved from service
     * 
     * This method triggers the following events automatically:
     * - TicketCreated: Fired when ticket is created (via Ticket model boot method)
     *   - Listener: SendTicketCreatedSms - Sends SMS notification to customer
     *   - Listener: BroadcastTicketCreated - Broadcasts to WebSocket channels
     *   - Job: QueueTicket - DISABLED - Tickets are not automatically queued
     * 
     * @param StoreTicketRequest $request
     * @return JsonResponse
     */
    public function store(StoreTicketRequest $request): JsonResponse
    {
        try {
            // Create ticket - this will automatically:
            // 1. Generate ticket number
            // 2. Find or create queue based on service and office
            // 3. Fire TicketCreated event which triggers SendTicketCreatedSms listener
            $ticket = $this->service->createTicket($request->validated());
            
            return $this->sendResponse($ticket, 'Ticket created successfully', [], 201);
        } catch (\Throwable $e) {
            Log::error('POST /api/qms/tickets failed', [
                'payload' => $request->all(),
                'exception' => get_class($e),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

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

    /**
     * Update a ticket.
     * 
     * This method triggers the following events automatically when status changes:
     * - TicketStatusChanged: Fired when status changes (via Ticket model boot method)
     * - TicketCompleted: Fired when status changes to 'completed'
     *   - Listener: SendTicketCompletedSms - Sends SMS notification to customer
     *   - Listener: BroadcastTicketCompleted - Broadcasts to WebSocket channels
     * - TicketCalled: Fired when status changes to 'called'
     * - TicketServing: Fired when status changes to 'serving'
     * 
     * @param UpdateTicketRequest $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(UpdateTicketRequest $request, string $id): JsonResponse
    {
        try {
            $ticket = $this->service->findById($id);

            if (!$ticket) {
                return $this->sendError('Ticket not found', [], 404);
            }

            // Update ticket - this will automatically fire events based on status changes
            // If status changes to 'completed', TicketCompleted event fires
            // which triggers SendTicketCompletedSms listener to send SMS notification
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


    public function callNextTicket(Request $request): JsonResponse
    {
        try {
            $ticket = $this->service->callNextTicket();
            return $this->sendResponse($ticket, 'Ticket called successfully');
        } catch (AuthenticationException $e) {
            return $this->sendError($e->getMessage(), [], 401);
        } catch (NotFoundHttpException $e) {
            return $this->sendError($e->getMessage(), [], 404);
        } catch (UnprocessableEntityHttpException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to call next ticket', ['error' => $e->getMessage()], 500);
        }
    }

}

