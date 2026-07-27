<?php

namespace App\Domains\Ticket\Controllers;

use App\Domains\Ticket\Requests\StoreTicketRequest;
use App\Domains\Ticket\Requests\SuspendTicketRequest;
use App\Domains\Ticket\Requests\UpdateTicketRequest;
use App\Domains\Ticket\Services\TicketAnnounceService;
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
                'tenant_id' => is_object($device) && isset($device->tenant_id)
                    ? (int) $device->tenant_id
                    : null,
                'office_id' => is_object($device) && isset($device->office_id)
                    ? (string) $device->office_id
                    : null,
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

            // Surface the real reason (e.g. no active counter) so kiosk can show it.
            return $this->sendError($e->getMessage() ?: 'Failed to create ticket', ['error' => $e->getMessage()], 500);
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
        } catch (UnprocessableEntityHttpException $e) {
            return $this->sendError($e->getMessage(), [], 422);
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
            $announceService = new TicketAnnounceService($this->service);
            $result = $announceService->requestCallNext();

            if (($result['status'] ?? '') === 'queued') {
                return $this->sendResponse($result, $result['message'] ?? 'Call queued', [], 202);
            }

            $ticket = $result['ticket'] ?? $result;
            return $this->sendResponse(
                array_merge(is_array($ticket) ? $ticket : [], [
                    'call_status' => 'called',
                    'announce_id' => $result['announce_id'] ?? null,
                ]),
                'Ticket called successfully'
            );
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

    public function pendingAnnounce(Request $request): JsonResponse
    {
        try {
            /** @var \App\Domains\Device\Models\Device $device */
            $device = $request->attributes->get('device');
            $announceService = new TicketAnnounceService($this->service);
            $job = $announceService->getPendingAnnounceForDevice($device);
            return $this->sendResponse($job, $job ? 'Pending announce retrieved' : 'No pending announce');
        } catch (UnprocessableEntityHttpException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve pending announce', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Long-poll announce: holds until a job is ready or timeout (default 20s).
     * Query: ?timeout=20 (max 25).
     */
    public function waitPendingAnnounce(Request $request): JsonResponse
    {
        try {
            /** @var \App\Domains\Device\Models\Device $device */
            $device = $request->attributes->get('device');
            $timeout = (int) $request->query('timeout', 20);
            $announceService = new TicketAnnounceService($this->service);
            $job = $announceService->waitPendingAnnounceForDevice($device, $timeout);

            if ($job === null) {
                // 200 + null keeps clients simple (204 with empty body is awkward for our JSON envelope).
                return $this->sendResponse(null, 'No pending announce (timeout)');
            }

            return $this->sendResponse($job, 'Pending announce ready');
        } catch (UnprocessableEntityHttpException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to wait for pending announce', ['error' => $e->getMessage()], 500);
        }
    }

    public function announceAck(Request $request): JsonResponse
    {
        try {
            /** @var \App\Domains\Device\Models\Device $device */
            $device = $request->attributes->get('device');
            $announceId = (string) $request->input('announce_id', '');
            if ($announceId === '') {
                return $this->sendError('announce_id is required', [], 422);
            }

            $announceService = new TicketAnnounceService($this->service);
            $result = $announceService->acknowledgeAnnounce($device, $announceId);
            return $this->sendResponse($result, 'Announce acknowledged');
        } catch (NotFoundHttpException $e) {
            return $this->sendError($e->getMessage(), [], 404);
        } catch (UnprocessableEntityHttpException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to acknowledge announce', ['error' => $e->getMessage()], 500);
        }
    }

    public function myPendingCall(): JsonResponse
    {
        try {
            $announceService = new TicketAnnounceService($this->service);
            $result = $announceService->getMyPendingCall();
            return $this->sendResponse($result, 'Pending call status retrieved');
        } catch (AuthenticationException $e) {
            return $this->sendError($e->getMessage(), [], 401);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve pending call', ['error' => $e->getMessage()], 500);
        }
    }

    public function cancelPendingCall(): JsonResponse
    {
        try {
            $announceService = new TicketAnnounceService($this->service);
            $count = $announceService->cancelMyPendingCalls();
            return $this->sendResponse(['cancelled' => $count], 'Pending calls cancelled');
        } catch (AuthenticationException $e) {
            return $this->sendError($e->getMessage(), [], 401);
        } catch (\Exception $e) {
            return $this->sendError('Failed to cancel pending calls', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Return the clerk's current incomplete ticket (called/serving/paused), if any.
     */
    public function activeTicket(): JsonResponse
    {
        try {
            $ticket = $this->service->getActiveClerkTicket();
            return $this->sendResponse(
                $ticket,
                $ticket ? 'Active ticket retrieved successfully' : 'No active ticket'
            );
        } catch (AuthenticationException $e) {
            return $this->sendError($e->getMessage(), [], 401);
        } catch (NotFoundHttpException $e) {
            return $this->sendError($e->getMessage(), [], 404);
        } catch (UnprocessableEntityHttpException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve active ticket', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Tickets needing clerk attention: transferred + further-notice holds.
     */
    public function attention(): JsonResponse
    {
        try {
            $tickets = $this->service->getAttentionTicketsForClerk();
            return $this->sendResponse($tickets, 'Attention tickets retrieved successfully');
        } catch (AuthenticationException $e) {
            return $this->sendError($e->getMessage(), [], 401);
        } catch (NotFoundHttpException $e) {
            return $this->sendError($e->getMessage(), [], 404);
        } catch (UnprocessableEntityHttpException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to retrieve attention tickets', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Hold a ticket: pause timer or until further notice.
     */
    public function hold(Request $request, string $id): JsonResponse
    {
        try {
            $mode = (string) $request->input('mode', '');
            $ticket = $this->service->holdTicket($id, $mode);
            return $this->sendResponse($ticket, 'Ticket held successfully');
        } catch (AuthenticationException $e) {
            return $this->sendError($e->getMessage(), [], 401);
        } catch (NotFoundHttpException $e) {
            return $this->sendError($e->getMessage(), [], 404);
        } catch (UnprocessableEntityHttpException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to hold ticket', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Resume a paused ticket back to serving.
     */
    public function resumePause(string $id): JsonResponse
    {
        try {
            $ticket = $this->service->resumePausedTicket($id);
            return $this->sendResponse($ticket, 'Paused ticket resumed successfully');
        } catch (AuthenticationException $e) {
            return $this->sendError($e->getMessage(), [], 401);
        } catch (NotFoundHttpException $e) {
            return $this->sendError($e->getMessage(), [], 404);
        } catch (UnprocessableEntityHttpException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to resume paused ticket', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Resume a transferred or further-notice hold ticket.
     */
    public function resume(string $id): JsonResponse
    {
        try {
            $ticket = $this->service->resumeAttentionTicket($id);
            return $this->sendResponse($ticket, 'Ticket resumed successfully');
        } catch (AuthenticationException $e) {
            return $this->sendError($e->getMessage(), [], 401);
        } catch (NotFoundHttpException $e) {
            return $this->sendError($e->getMessage(), [], 404);
        } catch (UnprocessableEntityHttpException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to resume ticket', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @deprecated Prefer resume(); kept for compatibility.
     */
    public function acceptTransfer(string $id): JsonResponse
    {
        return $this->resume($id);
    }

    /**
     * Mark a called ticket as no show.
     */
    public function noShow(string $id): JsonResponse
    {
        try {
            $ticket = $this->service->markTicketNoShow($id);
            return $this->sendResponse($ticket, 'Ticket marked as no show successfully');
        } catch (AuthenticationException $e) {
            return $this->sendError($e->getMessage(), [], 401);
        } catch (NotFoundHttpException $e) {
            return $this->sendError($e->getMessage(), [], 404);
        } catch (UnprocessableEntityHttpException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to mark ticket as no show', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Suspend a serving/paused ticket with a reason.
     */
    public function suspend(SuspendTicketRequest $request, string $id): JsonResponse
    {
        try {
            $ticket = $this->service->suspendTicket($id, (string) $request->validated('reason'));
            return $this->sendResponse($ticket, 'Ticket suspended successfully');
        } catch (AuthenticationException $e) {
            return $this->sendError($e->getMessage(), [], 401);
        } catch (NotFoundHttpException $e) {
            return $this->sendError($e->getMessage(), [], 404);
        } catch (UnprocessableEntityHttpException $e) {
            return $this->sendError($e->getMessage(), [], 422);
        } catch (\Exception $e) {
            return $this->sendError('Failed to suspend ticket', ['error' => $e->getMessage()], 500);
        }
    }

}

