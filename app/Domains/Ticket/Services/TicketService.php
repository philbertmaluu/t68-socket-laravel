<?php

namespace App\Domains\Ticket\Services;

use App\Domains\Authentication\Models\User;
use App\Domains\Counter\Models\CounterClerk;
use App\Domains\Counter\Models\Counter;
use App\Domains\Counter\Services\CounterService;
use App\Domains\Device\Models\Device;
use App\Domains\Device\Models\DeviceToken;
use App\Domains\Service\Models\Service;
use App\Domains\Service\Services\ServiceService;
use App\Domains\Queue\Models\Queue;
use App\Domains\Ticket\Models\Ticket;
use App\Domains\Ticket\Repositories\TicketRepository;
use App\Shared\Helpers\TransactionHelper;
use App\Traits\UserOfficeTrait;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class TicketService
{
    use UserOfficeTrait;

    private TicketRepository $repository;
    private ServiceService $serviceService;
    private CounterService $counterService;

    public function __construct()
    {
        $this->repository = new TicketRepository();
        $this->serviceService = new ServiceService();
        $this->counterService = new CounterService();
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

    /**
     * Create a new ticket with automatic queue assignment and ticket number generation.
     * 
     * Required fields in $data:
     * - service_type_id: The service ID
     * - phone_number: Customer phone number
     * - office_id: Office ID
     * 
     * Automatically generated:
     * - ticket_number: Auto-generated unique ticket number
     * - queue_id: Found or created based on service_type_id and office_id
     * - service_type: Retrieved from service name
     * - service_id: Set from service_type_id
     * - estimated_time: Retrieved from service if available
     * 
     * @param array $data
     * @return Ticket
     * @throws \Exception
     */
    public function createTicket(array $data): Ticket
    {
        return TransactionHelper::execute(function () use ($data) {
            // Idempotency: if the same request (phone + service + office) was created in the last 30 seconds, return that ticket (one ticket = one SMS).
            $existing = $this->repository->findRecentDuplicate(
                $data['phone_number'],
                (string) $data['service_type_id'],
                (string) $data['office_id'],
                30
            );
            if ($existing !== null) {
                return $existing;
            }
            // Get service information (must be assigned to this office via office_services)
            $service = $this->serviceService->findById($data['service_type_id']);

            if (!$service) {
                Log::error('Ticket create: service not found', [
                    'service_type_id' => $data['service_type_id'] ?? null,
                    'office_id' => $data['office_id'] ?? null,
                    'phone_number' => $data['phone_number'] ?? null,
                ]);
                throw new \Exception("Service with ID {$data['service_type_id']} not found");
            }

            if (!$this->serviceService->isServiceAssignedToOffice($data['service_type_id'], (string) $data['office_id'])) {
                Log::error('Ticket create: service not assigned to office', [
                    'service_type_id' => $data['service_type_id'] ?? null,
                    'office_id' => $data['office_id'] ?? null,
                ]);
                throw new \Exception("Service with ID {$data['service_type_id']} is not available for office {$data['office_id']}");
            }

            // Find a counter that can serve this service (or any active counter in the office)
            // Then find or create queue for that counter
            $queueId = $this->findOrCreateQueueForService($data['service_type_id'], $data['office_id']);

            // Generate ticket number
            $ticketNumber = $this->generateTicketNumber($data['office_id']);

            // Prepare ticket data
            // Note: tenant_id is automatically set by HasTenant trait if available
            $ticketData = [
                'ticket_number' => $ticketNumber,
                'service_type' => $service->name,
                'service_id' => $data['service_type_id'],
                'queue_id' => $queueId,
                'phone_number' => $data['phone_number'],
                'office_id' => $data['office_id'],
                'locale' => $data['locale'] ?? null,
                'estimated_time' => $service->estimated_time,
                'status' => 'waiting',
                'priority' => false,
                'created_by' => $this->resolveTicketCreatedBy(
                    $data['created_by'] ?? null,
                    (string) $data['office_id']
                ),
            ];

            // Set tenant_id if available from service
            if ($service->tenant_id) {
                $ticketData['tenant_id'] = $service->tenant_id;
            }

            // Create ticket
            return $this->repository->create($ticketData);
        });
    }

    /**
     * Find or create a queue for a service and office.
     *
     * Counters are linked to services via counter_services pivot (no service_id on counters).
     *
     * Logic:
     * 1. Find a counter that can serve this service (via counter_services pivot)
     * 2. If no counter found for this service, find any active counter in the office
     * 3. Get or create queue for that counter (1:1 relationship)
     *
     * @param string $serviceId
     * @param string $officeId
     * @return string Queue ID
     * @throws \Exception
     */
    private function findOrCreateQueueForService(string $serviceId, string $officeId): string
    {
        // Convert to string to ensure type consistency with database
        $serviceId = (string) $serviceId;
        $officeId = (string) $officeId;

        // Step 1: Find a counter that can serve this service (via counter_services pivot)
        $counter = Counter::forService($serviceId)
            ->where('office_id', $officeId)
            ->where('status', 'ACTIVE')
            ->first();

        // Step 2: If no counter found for this service, find any active counter in the office
        if (!$counter) {
            $counter = Counter::where('office_id', $officeId)
                ->where('status', 'ACTIVE')
                ->first();
        }
        
        if (!$counter) {
            Log::error('Ticket create: no active counter found', [
                'service_id' => $serviceId,
                'office_id' => $officeId,
            ]);
            throw new \Exception("No active counter found in office {$officeId} to serve service {$serviceId}");
        }
        
        // Step 3: Get or create queue for this counter (1:1 relationship)
        $queue = DB::table('queues')
            ->where('counter_id', $counter->id)
            ->first();
        
        if ($queue) {
            return (string) $queue->id; // Convert to string for consistency
        }
        
        // Create new queue for this counter (ID will be auto-generated)
        // Queues.status has an Oracle CHECK constraint: ('BUSY', 'NORMAL', 'CRITICAL', 'FREE')
        $queueId = DB::table('queues')->insertGetId([
            'counter_id' => $counter->id,
            'name' => $counter->name . ' Queue',
            'status' => 'NORMAL',
            'members_waiting' => 1,
            'members_being_served' => 0,
            'average_wait_time' => 0,
            'office_id' => $officeId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        return (string) $queueId; // Convert to string for consistency
    }

    /** @var int Max numeric suffix per letter block (matches voice assets A–Z and 1–500). */
    private const TICKET_NUM_MAX = 500;

    /**
     * Generate a unique ticket number.
     *
     * Format: one or more letters [A–Z] + digits 1–500 (unpadded), e.g. A1, A500, Z500, AA1, ZZ500, AAA1, …
     * Sequence: A1…A500, B1…B500, …, Z500, then AA1…AA500, …, ZZ500, then AAA1, … (unbounded prefix length).
     *
     * String sorting does not match sequence order, so the greatest ticket is found by parsing all numbers.
     * At very high volume consider a dedicated sequence column to avoid scanning ticket_number.
     *
     * @param string $officeId (reserved for future per-office sequences)
     */
    private function generateTicketNumber(string $officeId): string
    {
        $max = $this->findGreatestTicketNumber();

        if ($max === null) {
            return 'A1';
        }

        $parsed = $this->parseTicketNumber($max);
        if ($parsed === null) {
            return $this->findNextAvailableTicketNumber();
        }

        return $this->incrementTicketNumber($parsed['prefix'], $parsed['num']);
    }



    public function callNextTicket(): array
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user || !isset($user->id)) {
            throw new AuthenticationException('User not authenticated');
        }

        return $this->callNextTicketForUser($user);
    }

    /**
     * Call the next waiting ticket for a specific clerk (used by announce auto-call).
     */
    public function callNextTicketForUser(User $user): array
    {
        return TransactionHelper::execute(function () use ($user) {
            $location = $this->getUserOfficeAndRegionFromHrpForUser($user);
            $officeId = (string) $location['office_id'];
            $clerkIds = $this->resolveClerkIdentityCandidates($user);

            $activeTicket = $this->findActiveTicketForClerk($clerkIds, $officeId);
            if ($activeTicket) {
                throw new UnprocessableEntityHttpException(
                    'Complete the current ticket before calling the next one.'
                );
            }

            $counterAssignment = CounterClerk::query()
                ->whereIn('clerk_id', $clerkIds)
                ->where('is_active', true)
                ->latest('assigned_at')
                ->first();

            if (!$counterAssignment) {
                throw new UnprocessableEntityHttpException('User not assigned to a counter');
            }

            $counter = Counter::query()
                ->with('counterType')
                ->where('office_id', $officeId)
                ->find($counterAssignment->counter_id);

            if (!$counter) {
                throw new NotFoundHttpException('Assigned counter not found');
            }

            $queue = DB::table('queues')
                ->where('counter_id', $counter->id)
                ->first();

            if (!$queue) {
                throw new NotFoundHttpException('No queue found for assigned counter');
            }

            $ticket = Ticket::query()
                ->where('queue_id', (string) $queue->id)
                ->where('office_id', $officeId)
                ->where('status', 'waiting')
                ->orderBy('queue_position', 'asc')
                ->orderBy('created_at', 'asc')
                ->first();

            if (!$ticket) {
                throw new NotFoundHttpException('No waiting ticket found in queue');
            }

            $ticket->update([
                'status' => 'called',
                'counter_id' => (string) $counter->id,
                'clerk_id' => (string) $user->id,
                'called_at' => now(),
            ]);

            $ticket = $ticket->fresh();

            return $this->formatClerkTicketPayload($ticket, $counter);
        });
    }

    /**
     * Public wrapper for announce polling payloads.
     */
    public function formatClerkTicketPayloadPublic(Ticket $ticket, ?Counter $counter): array
    {
        return $this->formatClerkTicketPayload($ticket, $counter);
    }

    /**
     * Get the authenticated clerk's incomplete ticket (called or serving) for their HRPD office.
     */
    public function getActiveClerkTicket(): ?array
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user || !isset($user->id)) {
            throw new AuthenticationException('User not authenticated');
        }

        $location = $this->getUserOfficeAndRegionFromHrp();
        $officeId = (string) $location['office_id'];
        $clerkIds = $this->resolveClerkIdentityCandidates($user);

        $ticket = $this->findActiveTicketForClerk($clerkIds, $officeId);
        if (!$ticket) {
            return null;
        }

        $counter = null;
        if ($ticket->counter_id) {
            $counter = Counter::query()
                ->with('counterType')
                ->where('office_id', $officeId)
                ->find($ticket->counter_id);
        }

        if (!$counter) {
            $counterAssignment = CounterClerk::query()
                ->whereIn('clerk_id', $clerkIds)
                ->where('is_active', true)
                ->latest('assigned_at')
                ->first();

            if ($counterAssignment) {
                $counter = Counter::query()
                    ->with('counterType')
                    ->where('office_id', $officeId)
                    ->find($counterAssignment->counter_id);
            }
        }

        return $this->formatClerkTicketPayload($ticket, $counter);
    }

    /**
     * Lightweight attention list: transferred-to-me + further-notice holds.
     *
     * @return list<array<string, mixed>>
     */
    public function getAttentionTicketsForClerk(): array
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user || !isset($user->id)) {
            throw new AuthenticationException('User not authenticated');
        }

        $location = $this->getUserOfficeAndRegionFromHrp();
        $officeId = (string) $location['office_id'];
        $clerkIds = $this->resolveClerkIdentityCandidates($user);
        $counterAssignment = $this->resolveActiveCounterAssignment($clerkIds, $officeId);
        $myCounterId = (string) $counterAssignment->counter_id;

        $tickets = Ticket::query()
            ->select([
                'id',
                'ticket_number',
                'service_type',
                'estimated_time',
                'status',
                'counter_id',
                'clerk_id',
                'transferred_to_counter_id',
                'updated_at',
            ])
            ->where('office_id', $officeId)
            ->where(function ($q) use ($myCounterId, $clerkIds) {
                $q->where(function ($t) use ($myCounterId) {
                    $t->where('status', 'transferred')
                        ->where('transferred_to_counter_id', $myCounterId);
                })->orWhere(function ($h) use ($myCounterId, $clerkIds) {
                    $h->where('status', 'hold')
                        ->where('counter_id', $myCounterId)
                        ->whereIn('clerk_id', $clerkIds);
                });
            })
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get();

        if ($tickets->isEmpty()) {
            return [];
        }

        $counterIds = $tickets->pluck('counter_id')->filter()->unique()->values();
        $sourceClerkIds = $tickets->pluck('clerk_id')->filter()->unique()->values();

        $counterNames = $counterIds->isEmpty()
            ? collect()
            : Counter::query()->whereIn('id', $counterIds)->pluck('name', 'id');

        $clerkNames = $sourceClerkIds->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $sourceClerkIds)->pluck('name', 'id');

        return $tickets->map(function (Ticket $ticket) use ($counterNames, $clerkNames) {
            $type = $ticket->status === 'hold' ? 'hold' : 'transferred';

            return [
                'id' => $ticket->id,
                'type' => $type,
                'ticket_number' => $ticket->ticket_number,
                'service_type' => $ticket->service_type,
                'estimated_time' => $ticket->estimated_time,
                'from_counter' => $counterNames->get((string) $ticket->counter_id) ?? 'Unknown Counter',
                'from_clerk' => $clerkNames->get((string) $ticket->clerk_id) ?? 'Unknown Clerk',
                'attention_at' => $ticket->updated_at,
            ];
        })->values()->all();
    }

    /**
     * Pause timer (paused) or hold until further notice (hold).
     *
     * @param 'pause'|'further_notice' $mode
     */
    public function holdTicket(string $ticketId, string $mode): array
    {
        return TransactionHelper::execute(function () use ($ticketId, $mode) {
            $user = Auth::guard('sanctum')->user();
            if (!$user || !isset($user->id)) {
                throw new AuthenticationException('User not authenticated');
            }

            if (!in_array($mode, ['pause', 'further_notice'], true)) {
                throw new UnprocessableEntityHttpException('Invalid hold mode');
            }

            $location = $this->getUserOfficeAndRegionFromHrp();
            $officeId = (string) $location['office_id'];
            $clerkIds = $this->resolveClerkIdentityCandidates($user);

            $ticket = Ticket::query()
                ->where('id', $ticketId)
                ->where('office_id', $officeId)
                ->first();

            if (!$ticket) {
                throw new NotFoundHttpException('Ticket not found');
            }

            if (!in_array((string) $ticket->clerk_id, $clerkIds, true)) {
                throw new UnprocessableEntityHttpException('Ticket is not assigned to you');
            }

            if (!in_array($ticket->status, ['called', 'serving'], true)) {
                throw new UnprocessableEntityHttpException('Ticket cannot be held in its current status');
            }

            $newStatus = $mode === 'pause' ? 'paused' : 'hold';
            $ticket->update(['status' => $newStatus]);

            return [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'status' => $newStatus,
                'mode' => $mode,
            ];
        });
    }

    /**
     * Resume a paused ticket back to serving (timer continues on frontend).
     */
    public function resumePausedTicket(string $ticketId): array
    {
        return TransactionHelper::execute(function () use ($ticketId) {
            $user = Auth::guard('sanctum')->user();
            if (!$user || !isset($user->id)) {
                throw new AuthenticationException('User not authenticated');
            }

            $location = $this->getUserOfficeAndRegionFromHrp();
            $officeId = (string) $location['office_id'];
            $clerkIds = $this->resolveClerkIdentityCandidates($user);

            $ticket = Ticket::query()
                ->where('id', $ticketId)
                ->where('office_id', $officeId)
                ->first();

            if (!$ticket) {
                throw new NotFoundHttpException('Ticket not found');
            }

            if (!in_array((string) $ticket->clerk_id, $clerkIds, true)) {
                throw new UnprocessableEntityHttpException('Ticket is not assigned to you');
            }

            if ($ticket->status !== 'paused') {
                throw new UnprocessableEntityHttpException('Ticket is not paused');
            }

            $ticket->update(['status' => 'serving']);

            $counter = null;
            if ($ticket->counter_id) {
                $counter = Counter::query()
                    ->with('counterType')
                    ->where('office_id', $officeId)
                    ->find($ticket->counter_id);
            }

            return $this->formatClerkTicketPayload($ticket->fresh(), $counter);
        });
    }

    /**
     * Claim a transferred or further-notice hold ticket into the clerk's active slot.
     */
    public function resumeAttentionTicket(string $ticketId): array
    {
        return TransactionHelper::execute(function () use ($ticketId) {
            $user = Auth::guard('sanctum')->user();
            if (!$user || !isset($user->id)) {
                throw new AuthenticationException('User not authenticated');
            }

            $location = $this->getUserOfficeAndRegionFromHrp();
            $officeId = (string) $location['office_id'];
            $clerkIds = $this->resolveClerkIdentityCandidates($user);
            $counterAssignment = $this->resolveActiveCounterAssignment($clerkIds, $officeId);
            $myCounterId = (string) $counterAssignment->counter_id;

            $activeTicket = $this->findActiveTicketForClerk($clerkIds, $officeId);
            if ($activeTicket) {
                throw new UnprocessableEntityHttpException(
                    'Complete the current ticket before resuming another.'
                );
            }

            $ticket = Ticket::query()
                ->where('id', $ticketId)
                ->where('office_id', $officeId)
                ->first();

            if (!$ticket) {
                throw new NotFoundHttpException('Ticket not found');
            }

            if ($ticket->status === 'transferred') {
                if ((string) $ticket->transferred_to_counter_id !== $myCounterId) {
                    throw new UnprocessableEntityHttpException('Ticket was not transferred to your counter');
                }
            } elseif ($ticket->status === 'hold') {
                if ((string) $ticket->counter_id !== $myCounterId
                    || !in_array((string) $ticket->clerk_id, $clerkIds, true)
                ) {
                    throw new UnprocessableEntityHttpException('Ticket is not on hold for your counter');
                }
            } else {
                throw new UnprocessableEntityHttpException('Ticket is not pending attention');
            }

            $counter = Counter::query()
                ->with('counterType')
                ->where('office_id', $officeId)
                ->find($myCounterId);

            if (!$counter) {
                throw new NotFoundHttpException('Assigned counter not found');
            }

            $queue = Queue::query()
                ->where('counter_id', $counter->id)
                ->first();

            if (!$queue) {
                throw new NotFoundHttpException('No queue found for assigned counter');
            }

            $ticket->update([
                'status' => 'called',
                'counter_id' => $myCounterId,
                'clerk_id' => (string) $user->id,
                'queue_id' => (string) $queue->id,
                'called_at' => now(),
            ]);

            return $this->formatClerkTicketPayload($ticket->fresh(), $counter);
        });
    }

    /** @deprecated Use resumeAttentionTicket */
    public function acceptTransferredTicket(string $ticketId): array
    {
        return $this->resumeAttentionTicket($ticketId);
    }

    /**
     * Mark a called ticket as no_show when the customer does not appear.
     */
    public function markTicketNoShow(string $ticketId): array
    {
        return TransactionHelper::execute(function () use ($ticketId) {
            $user = Auth::guard('sanctum')->user();
            if (!$user || !isset($user->id)) {
                throw new AuthenticationException('User not authenticated');
            }

            $location = $this->getUserOfficeAndRegionFromHrp();
            $officeId = (string) $location['office_id'];
            $clerkIds = $this->resolveClerkIdentityCandidates($user);

            $ticket = Ticket::query()
                ->where('id', $ticketId)
                ->where('office_id', $officeId)
                ->first();

            if (!$ticket) {
                throw new NotFoundHttpException('Ticket not found');
            }

            if (!in_array((string) $ticket->clerk_id, $clerkIds, true)) {
                throw new UnprocessableEntityHttpException('Ticket is not assigned to you');
            }

            if ($ticket->status !== 'called') {
                throw new UnprocessableEntityHttpException(
                    'Only a called ticket can be marked as no show'
                );
            }

            $ticket->update(['status' => 'no_show']);

            return [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'status' => 'no_show',
            ];
        });
    }

    /**
     * Suspend an in-progress ticket with a required reason.
     */
    public function suspendTicket(string $ticketId, string $reason): array
    {
        return TransactionHelper::execute(function () use ($ticketId, $reason) {
            $user = Auth::guard('sanctum')->user();
            if (!$user || !isset($user->id)) {
                throw new AuthenticationException('User not authenticated');
            }

            $trimmedReason = trim($reason);
            if (mb_strlen($trimmedReason) < 3) {
                throw new UnprocessableEntityHttpException('Suspension reason is required');
            }

            $location = $this->getUserOfficeAndRegionFromHrp();
            $officeId = (string) $location['office_id'];
            $clerkIds = $this->resolveClerkIdentityCandidates($user);

            $ticket = Ticket::query()
                ->where('id', $ticketId)
                ->where('office_id', $officeId)
                ->first();

            if (!$ticket) {
                throw new NotFoundHttpException('Ticket not found');
            }

            if (!in_array((string) $ticket->clerk_id, $clerkIds, true)) {
                throw new UnprocessableEntityHttpException('Ticket is not assigned to you');
            }

            if (!in_array($ticket->status, ['serving', 'paused'], true)) {
                throw new UnprocessableEntityHttpException(
                    'Only a serving or paused ticket can be suspended'
                );
            }

            $payload = [
                'status' => 'suspended',
                'suspension_reason' => $trimmedReason,
            ];

            if ($ticket->serving_started_at) {
                $payload['duration_seconds'] = max(
                    0,
                    now()->diffInSeconds($ticket->serving_started_at)
                );
            }

            $ticket->update($payload);

            return [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'status' => 'suspended',
                'suspension_reason' => $trimmedReason,
            ];
        });
    }

    /**
     * Resolve clerk identity candidates used across counter assignments and tickets.
     *
     * @return list<string>
     */
    private function resolveClerkIdentityCandidates(object $user): array
    {
        return array_values(array_filter([
            isset($user->id) ? (string) $user->id : null,
            isset($user->pfno) ? (string) $user->pfno : null,
            isset($user->user_id) ? (string) $user->user_id : null,
            isset($user->username) ? (string) $user->username : null,
            isset($user->email) ? (string) $user->email : null,
        ]));
    }

    /**
     * Find incomplete ticket for this clerk in the given office.
     *
     * @param list<string> $clerkIds
     */
    private function findActiveTicketForClerk(array $clerkIds, string $officeId): ?Ticket
    {
        if ($clerkIds === [] || $officeId === '') {
            return null;
        }

        return Ticket::query()
            ->whereIn('clerk_id', $clerkIds)
            ->where('office_id', $officeId)
            ->whereIn('status', ['called', 'serving', 'paused'])
            ->orderByDesc('called_at')
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * @param list<string> $clerkIds
     */
    private function resolveActiveCounterAssignment(array $clerkIds, string $officeId): CounterClerk
    {
        $counterAssignment = CounterClerk::query()
            ->whereIn('clerk_id', $clerkIds)
            ->where('is_active', true)
            ->latest('assigned_at')
            ->first();

        if (!$counterAssignment) {
            throw new UnprocessableEntityHttpException('User not assigned to a counter');
        }

        $counter = Counter::query()
            ->where('office_id', $officeId)
            ->find($counterAssignment->counter_id);

        if (!$counter) {
            throw new NotFoundHttpException('Assigned counter not found');
        }

        return $counterAssignment;
    }

    /**
     * Set created_by from sanctum user, authenticated device, explicit value, or kiosk office fallback.
     */
    private function resolveTicketCreatedBy(mixed $explicit, string $officeId): string
    {
        if (is_string($explicit) && trim($explicit) !== '') {
            return trim($explicit);
        }

        $user = Auth::guard('sanctum')->user();
        if ($user instanceof User && isset($user->id)) {
            return (string) $user->id;
        }

        $device = request()->attributes->get('device');
        if (is_object($device) && isset($device->id)) {
            return (string) $device->id;
        }

        $token = request()->header('X-Device-Token');
        if (!$token) {
            $authHeader = request()->header('Authorization');
            if (is_string($authHeader) && str_starts_with($authHeader, 'Bearer ')) {
                $token = substr($authHeader, 7);
            }
        }

        if (is_string($token) && $token !== '') {
            $tokenModel = DeviceToken::query()->where('token', $token)->first();
            if ($tokenModel && !$tokenModel->isExpired() && $tokenModel->device) {
                return (string) $tokenModel->device->id;
            }
        }

        return 'kiosk:' . $officeId;
    }

    private function formatClerkTicketPayload(Ticket $ticket, ?Counter $counter): array
    {
        return [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'service_type' => $ticket->service_type,
            'estimated_time' => $ticket->estimated_time,
            'status' => $ticket->status,
            'called_at' => $ticket->called_at,
            'serving_started_at' => $ticket->serving_started_at,
            'counter' => [
                'id' => $counter?->id,
                'name' => $counter?->name,
                'counter_type' => [
                    'id' => $counter?->counterType?->id,
                    'name' => $counter?->counterType?->name,
                    'code' => $counter?->counterType?->code,
                ],
            ],
        ];
    }


    public function getClerksTickets(array $filters = []): array
    {
        return TransactionHelper::execute(function () use ($filters) {
            $user = Auth::guard('sanctum')->user();
            if (!$user || !isset($user->id)) {
                throw new AuthenticationException('User not authenticated');
            }

            $location = $this->getUserOfficeAndRegionFromHrp();
            $officeId = (string) $location['office_id'];
            $officeName = $location['office_name'] ?? null;

            $counterAssignment = CounterClerk::query()
                ->where('clerk_id', (string) $user->id)
                ->where('is_active', true)
                ->latest('assigned_at')
                ->first();

            if (!$counterAssignment) {
                throw new NotFoundHttpException('User not assigned to a counter');
            }

            $counterId = (string) $counterAssignment->counter_id;
            $queueId = $counterAssignment->queue_id
                ? (string) $counterAssignment->queue_id
                : null;

            if (!$queueId) {
                $queue = DB::table('queues')
                    ->where('counter_id', $counterId)
                    ->where('office_id', $officeId)
                    ->first();

                if (!$queue) {
                    throw new NotFoundHttpException('No queue found for assigned counter');
                }

                $queueId = (string) $queue->id;
            }
            $clerkId = (string) $user->id;
            $scope = strtolower((string) ($filters['scope'] ?? 'all'));

            $ticketsQuery = Ticket::query()
                ->where('office_id', $officeId)
                ->where('queue_id', $queueId);

            // Waiting tickets are counter-level (no clerk yet), history is clerk-level.
            if ($scope === 'waiting') {
                $ticketsQuery->where('status', 'waiting');
            } elseif ($scope === 'history') {
                $ticketsQuery
                    ->where('status', '!=', 'waiting')
                    ->where('clerk_id', $clerkId);
            } else {
                $ticketsQuery->where(function ($query) use ($clerkId) {
                    $query
                        ->where('status', 'waiting')
                        ->orWhere(function ($historyQuery) use ($clerkId) {
                            $historyQuery
                                ->where('status', '!=', 'waiting')
                                ->where('clerk_id', $clerkId);
                        });
                });
            }

            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $ticketsQuery->where('status', strtolower((string) $filters['status']));
            }

            if (!empty($filters['date_from'])) {
                $ticketsQuery->whereDate('created_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $ticketsQuery->whereDate('created_at', '<=', $filters['date_to']);
            }

            if (!empty($filters['search'])) {
                $search = trim((string) $filters['search']);
                $ticketsQuery->where(function ($query) use ($search) {
                    $query
                        ->where('ticket_number', 'like', "%{$search}%")
                        ->orWhere('service_type', 'like', "%{$search}%")
                        ->orWhere('member_name', 'like', "%{$search}%")
                        ->orWhere('member_number', 'like', "%{$search}%");
                });
            }

            $tickets = (clone $ticketsQuery)
                ->orderByDesc('created_at')
                ->get([
                    'id',
                    'ticket_number',
                    'service_type',
                    'clerk_id',
                    'status',
                    'called_at',
                    'serving_started_at as start_time',
                    'serving_started_at',
                    'completed_at',
                    'duration_seconds',
                    'transferred_to_counter_id',
                    'office_id',
                    'created_at',
                    'updated_at',
                ]);

            $tickets->each(function (Ticket $ticket) use ($officeName) {
                $ticket->setAttribute('office_name', $officeName);
            });

            $statusCounts = (clone $ticketsQuery)
                ->selectRaw('LOWER(status) as status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $totalDurationSeconds = (int) ((clone $ticketsQuery)->sum('duration_seconds') ?? 0);
            $avgDurationSeconds = (int) round(
                (clone $ticketsQuery)
                    ->whereNotNull('duration_seconds')
                    ->where('duration_seconds', '>', 0)
                    ->avg('duration_seconds') ?? 0
            );

            return [
                'tickets' => $tickets,
                'summary' => [
                    'scope' => $scope,
                    'office_id' => $officeId,
                    'office_name' => $officeName,
                    'queue_id' => $queueId,
                    'counter_id' => $counterId,
                    'clerk_id' => $clerkId,
                    'total_tickets' => (int) ((clone $ticketsQuery)->count()),
                    'total_waiting_tickets' => (int) ($statusCounts['waiting'] ?? 0),
                    'total_called_tickets' => (int) ($statusCounts['called'] ?? 0),
                    'total_serving_tickets' => (int) ($statusCounts['serving'] ?? 0),
                    'total_completed_tickets' => (int) ($statusCounts['completed'] ?? 0),
                    'total_skipped_tickets' => (int) ($statusCounts['skipped'] ?? 0),
                    'total_no_show_tickets' => (int) ($statusCounts['no_show'] ?? 0),
                    'total_transferred_tickets' => (int) ($statusCounts['transferred'] ?? 0),
                    'total_suspend_tickets' => (int) (
                        ($statusCounts['suspended'] ?? 0) + ($statusCounts['suspend'] ?? 0)
                    ),
                    'total_cancelled_tickets' => (int) ($statusCounts['cancelled'] ?? 0),
                    'total_duration_seconds' => $totalDurationSeconds,
                    'avg_duration_seconds' => $avgDurationSeconds,
                    'status_breakdown' => $statusCounts
                        ->mapWithKeys(fn ($count, $status) => [strtolower((string) $status) => (int) $count])
                        ->toArray(),
                ],
            ];
        });
    }

    /**
     * TV / device display board: waiting + currently serving tickets for the
     * authenticated device's office (and tenant).
     *
     * @param  array{device_id?: string, office_id?: string, tenant_id?: int|string}  $filters
     * @return array{
     *   office_id: string,
     *   office_name: string|null,
     *   current_tickets: list<array<string, mixed>>,
     *   waiting_tickets: list<array<string, mixed>>,
     *   summary: array{total_current_tickets: int, total_waiting_tickets: int}
     * }
     */
    public function getWaitingAndServingTicketsPerOffice(array $filters = []): array
    {
        $deviceId = isset($filters['device_id']) ? trim((string) $filters['device_id']) : '';
        $officeId = null;
        $tenantId = isset($filters['tenant_id']) ? (int) $filters['tenant_id'] : null;

        if ($deviceId !== '') {
            $device = Device::withoutGlobalScope('tenant')
                ->select(['id', 'office_id', 'tenant_id'])
                ->find($deviceId);

            if ($device) {
                $officeId = $device->office_id ? (string) $device->office_id : null;
                if ($tenantId === null && !empty($device->tenant_id)) {
                    $tenantId = (int) $device->tenant_id;
                }
            }
        }

        if (!$officeId && isset($filters['office_id'])) {
            $officeId = trim((string) $filters['office_id']) ?: null;
        }

        if (!$officeId) {
            throw new UnprocessableEntityHttpException(
                'Unable to resolve office_id from authenticated device'
            );
        }

        if ($tenantId) {
            app()->instance('tenant.id', $tenantId);
        }

        $baseQuery = Ticket::withoutGlobalScope('tenant')
            ->leftJoin('queues as q', 'q.id', '=', 'tickets.queue_id')
            ->leftJoin('counters as c', 'c.id', '=', 'tickets.counter_id')
            ->leftJoin('counter_types as ct', 'ct.id', '=', 'c.counter_type_id')
            ->where('tickets.office_id', $officeId)
            ->when($tenantId, fn ($q) => $q->where('tickets.tenant_id', $tenantId));

        $select = [
            'tickets.id',
            'tickets.ticket_number',
            'tickets.service_type',
            'tickets.status',
            'tickets.queue_id',
            'tickets.counter_id',
            'tickets.queue_position',
            'tickets.called_at',
            'tickets.serving_started_at',
            'tickets.created_at',
            DB::raw('q.name as queue_name'),
            DB::raw('c.name as counter_name'),
            DB::raw('ct.name as counter_type_name'),
            DB::raw('ct.code as counter_type_code'),
        ];

        $currentRows = (clone $baseQuery)
            ->whereIn('tickets.status', ['serving', 'called'])
            ->orderByRaw("CASE tickets.status WHEN 'serving' THEN 0 WHEN 'called' THEN 1 ELSE 2 END")
            ->orderByDesc('tickets.called_at')
            ->orderByDesc('tickets.updated_at')
            ->get($select);

        $waitingRows = (clone $baseQuery)
            ->where('tickets.status', 'waiting')
            ->orderBy('tickets.queue_position')
            ->orderBy('tickets.created_at')
            ->get($select);

        $currentTickets = $currentRows->map(fn ($row) => $this->formatTvTicketRow($row))->values()->all();
        $waitingTickets = $waitingRows->map(fn ($row) => $this->formatTvTicketRow($row))->values()->all();

        return [
            'office_id' => (string) $officeId,
            'office_name' => $this->resolveOfficeName((string) $officeId),
            'current_tickets' => $currentTickets,
            'waiting_tickets' => $waitingTickets,
            'summary' => [
                'total_current_tickets' => count($currentTickets),
                'total_waiting_tickets' => count($waitingTickets),
            ],
        ];
    }

    /**
     * @param  object  $row
     * @return array<string, mixed>
     */
    private function formatTvTicketRow(object $row): array
    {
        $counterName = isset($row->counter_name) && trim((string) $row->counter_name) !== ''
            ? (string) $row->counter_name
            : (isset($row->queue_name) && trim((string) $row->queue_name) !== ''
                ? (string) $row->queue_name
                : ($row->counter_id ? 'Counter '.$row->counter_id : null));

        $counterTypeName = isset($row->counter_type_name) && trim((string) $row->counter_type_name) !== ''
            ? (string) $row->counter_type_name
            : null;
        $counterTypeCode = isset($row->counter_type_code) && trim((string) $row->counter_type_code) !== ''
            ? (string) $row->counter_type_code
            : null;

        return [
            'id' => (string) $row->id,
            'ticket_number' => (string) $row->ticket_number,
            'service_type' => $row->service_type ? (string) $row->service_type : null,
            'status' => (string) $row->status,
            'queue_id' => $row->queue_id !== null ? (string) $row->queue_id : null,
            'queue_name' => isset($row->queue_name) && $row->queue_name !== null
                ? (string) $row->queue_name
                : null,
            'counter_id' => $row->counter_id !== null ? (string) $row->counter_id : null,
            'counter_name' => $counterName,
            'counter_type_name' => $counterTypeName,
            'counter_type_code' => $counterTypeCode,
            'queue_position' => isset($row->queue_position) ? (int) $row->queue_position : null,
            'called_at' => isset($row->called_at) && $row->called_at
                ? (string) $row->called_at
                : null,
            'serving_started_at' => isset($row->serving_started_at) && $row->serving_started_at
                ? (string) $row->serving_started_at
                : null,
            'created_at' => isset($row->created_at) && $row->created_at
                ? (string) $row->created_at
                : null,
        ];
    }

    private function resolveOfficeName(string $officeId): ?string
    {
        try {
            $name = DB::table('hrpd.office')
                ->where('office_id', $officeId)
                ->value('office_name');

            return $name ? (string) $name : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{prefix: string, num: int}|null
     */
    private function parseTicketNumber(string $ticketNumber): ?array
    {
        if (!preg_match('/^([A-Z]+)(\d+)$/', $ticketNumber, $m)) {
            return null;
        }

        $num = (int) $m[2];
        if ($num < 1 || $num > self::TICKET_NUM_MAX) {
            return null;
        }

        return ['prefix' => $m[1], 'num' => $num];
    }

    /**
     * Order: A…Z (len 1), then AA…ZZ (len 2), then AAA…, comparing length then lexicographically.
     *
     * @return int -1 if a < b, 0 if equal, 1 if a > b
     */
    private function compareTicketSequence(string $prefixA, int $numA, string $prefixB, int $numB): int
    {
        $lenA = strlen($prefixA);
        $lenB = strlen($prefixB);
        if ($lenA !== $lenB) {
            return $lenA <=> $lenB;
        }

        $cmp = strcmp($prefixA, $prefixB);
        if ($cmp !== 0) {
            return $cmp <=> 0;
        }

        return $numA <=> $numB;
    }

    private function findGreatestTicketNumber(): ?string
    {
        $best = null;
        /** @var string|null $bestPrefix */
        $bestPrefix = null;
        $bestNum = 0;

        foreach (Ticket::pluck('ticket_number') as $tn) {
            $parsed = $this->parseTicketNumber($tn);
            if ($parsed === null) {
                continue;
            }

            if ($best === null
                || $this->compareTicketSequence($parsed['prefix'], $parsed['num'], $bestPrefix, $bestNum) > 0) {
                $best = $tn;
                $bestPrefix = $parsed['prefix'];
                $bestNum = $parsed['num'];
            }
        }

        return $best;
    }

    private function incrementTicketNumber(string $prefix, int $num): string
    {
        if ($num < self::TICKET_NUM_MAX) {
            return $prefix . (string) ($num + 1);
        }

        $nextPrefix = $this->nextPrefixInSequence($prefix);

        return $nextPrefix . '1';
    }

    /**
     * Next prefix after A, B, …, Z, AA, AB, …, AZ, BA, …, ZZ, AAA, …
     */
    private function nextPrefixInSequence(string $prefix): string
    {
        $len = strlen($prefix);
        $chars = str_split($prefix);

        for ($i = $len - 1; $i >= 0; $i--) {
            if ($chars[$i] < 'Z') {
                $chars[$i] = chr(ord($chars[$i]) + 1);
                for ($j = $i + 1; $j < $len; $j++) {
                    $chars[$j] = 'A';
                }

                return implode('', $chars);
            }
        }

        return str_repeat('A', $len + 1);
    }

    /**
     * When the numeric sequence is exhausted, find the smallest ticket not present (prefix order, then 1–500).
     */
    private function findNextAvailableTicketNumber(): string
    {
        $existing = array_flip(Ticket::pluck('ticket_number')->all());
        $prefix = 'A';

        for ($guard = 0; $guard < 100000; $guard++) {
            for ($num = 1; $num <= self::TICKET_NUM_MAX; $num++) {
                $candidate = $prefix . $num;
                if (!isset($existing[$candidate])) {
                    return $candidate;
                }
            }
            $prefix = $this->nextPrefixInSequence($prefix);
        }

        return 'A1';
    }

    public function updateTicket(Ticket $ticket, array $data): Ticket
    {
        return TransactionHelper::execute(function () use ($ticket, $data) {
            $newStatus = isset($data['status']) ? strtolower((string) $data['status']) : null;

            if ($newStatus === 'serving') {
                if (empty($data['serving_started_at'])) {
                    $data['serving_started_at'] = now();
                }
            }

            if ($newStatus === 'transferred') {
                $targetCounterId = $data['transferred_to_counter_id'] ?? null;
                if (empty($targetCounterId)) {
                    throw new UnprocessableEntityHttpException(
                        'transferred_to_counter_id is required when transferring a ticket'
                    );
                }

                $targetCounter = Counter::query()
                    ->where('id', $targetCounterId)
                    ->where('office_id', $ticket->office_id)
                    ->first();

                if (!$targetCounter) {
                    throw new UnprocessableEntityHttpException('Target counter not found in this office');
                }
            }

            if ($newStatus === 'completed') {
                if (empty($data['completed_at'])) {
                    $data['completed_at'] = now();
                }

                if (!isset($data['duration_seconds']) || $data['duration_seconds'] === null) {
                    $startedAt = $ticket->serving_started_at
                        ?? (!empty($data['serving_started_at']) ? $data['serving_started_at'] : null);

                    if ($startedAt) {
                        $started = $startedAt instanceof \DateTimeInterface
                            ? \Carbon\Carbon::instance($startedAt)
                            : \Carbon\Carbon::parse($startedAt);
                        $ended = !empty($data['completed_at'])
                            ? \Carbon\Carbon::parse($data['completed_at'])
                            : now();
                        $data['duration_seconds'] = max(0, $ended->diffInSeconds($started));
                    }
                }

                // If Serve was skipped, still stamp serving start when completing.
                if (empty($ticket->serving_started_at) && empty($data['serving_started_at'])) {
                    $data['serving_started_at'] = $ticket->called_at ?? now();
                }
            }

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
