<?php

namespace App\Domains\Ticket\Services;

use App\Domains\Counter\Models\Counter;
use App\Domains\Counter\Services\CounterService;
use App\Domains\Service\Models\Service;
use App\Domains\Service\Services\ServiceService;
use App\Domains\Ticket\Models\Ticket;
use App\Domains\Ticket\Repositories\TicketRepository;
use App\Shared\Helpers\TransactionHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TicketService
{
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
            // Get service information
            $service = $this->serviceService->findById($data['service_type_id']);
            
            if (!$service) {
                throw new \Exception("Service with ID {$data['service_type_id']} not found");
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
