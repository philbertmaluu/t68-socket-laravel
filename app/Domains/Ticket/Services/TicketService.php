<?php

namespace App\Domains\Ticket\Services;

use App\Domains\Service\Models\Service;
use App\Domains\Service\Services\ServiceService;
use App\Domains\Ticket\Models\Ticket;
use App\Domains\Ticket\Repositories\TicketRepository;
use App\Shared\Helpers\TransactionHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketService
{
    private TicketRepository $repository;
    private ServiceService $serviceService;

    public function __construct()
    {
        $this->repository = new TicketRepository();
        $this->serviceService = new ServiceService();
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
            // Get service information
            $service = $this->serviceService->findById($data['service_type_id']);
            
            if (!$service) {
                throw new \Exception("Service with ID {$data['service_type_id']} not found");
            }

            // Find or create queue for this service and office
            $queueId = $this->findOrCreateQueue($data['service_type_id'], $data['office_id'], $service->name);

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
     * @param string $serviceId
     * @param string $officeId
     * @param string $serviceName
     * @return string Queue ID
     */
    private function findOrCreateQueue(string $serviceId, string $officeId, string $serviceName): string
    {
        // Try to find existing queue for this service and office
        $queue = DB::table('queues')
            ->where('service_id', $serviceId)
            ->where('office_id', $officeId)
            ->first();

        if ($queue) {
            return $queue->id;
        }

        // Create new queue if it doesn't exist
        $queueId = 'queue-' . $serviceId . '-' . $officeId . '-' . time();
        
        DB::table('queues')->insert([
            'id' => $queueId,
            'name' => $serviceName . ' Queue',
            'service_type' => $serviceName,
            'service_id' => $serviceId,
            'office_id' => $officeId,
            'status' => 'normal',
            'members_waiting' => 0,
            'members_being_served' => 0,
            'average_wait_time' => 0,
            'counters' => 0,
            'active_counters' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('Created new queue', [
            'queue_id' => $queueId,
            'service_id' => $serviceId,
            'office_id' => $officeId,
        ]);

        return $queueId;
    }

    /**
     * Generate a unique ticket number.
     * 
     * Format: {LETTER}{3-DIGIT-NUMBER} (4 characters total)
     * Examples: A001, A002, ..., A999, B001, B002, ..., Z999
     * 
     * Pattern:
     * - Starts with A001, increments to A999 (999 tickets)
     * - Then B001 to B999 (999 tickets)
     * - Continues through Z001 to Z999 (999 tickets)
     * - Total capacity: 26 letters × 999 numbers = 25,974 unique tickets
     * 
     * Duration Estimate:
     * - At 100 tickets/day: ~260 days (8.5 months)
     * - At 200 tickets/day: ~130 days (4.3 months)
     * - At 500 tickets/day: ~52 days (1.7 months)
     * - At 1000 tickets/day: ~26 days
     * 
     * After Z999, the system will check for the oldest unused ticket number
     * starting from A001 and reuse it (ensuring no active tickets have that number).
     * 
     * @param string $officeId (not used in new format, kept for compatibility)
     * @return string
     */
    private function generateTicketNumber(string $officeId): string
    {
        // Get the last ticket number in the system
        $lastTicket = Ticket::orderBy('ticket_number', 'desc')
            ->first();

        if (!$lastTicket || !$lastTicket->ticket_number) {
            // First ticket ever
            return 'A001';
        }

        $lastNumber = $lastTicket->ticket_number;

        // Extract letter and number from last ticket (format: A001)
        if (preg_match('/^([A-Z])(\d{3})$/', $lastNumber, $matches)) {
            $lastLetter = $matches[1];
            $lastNum = (int) $matches[2];

            // If we haven't reached 999 for this letter, increment number
            if ($lastNum < 999) {
                $newNum = $lastNum + 1;
                return $lastLetter . str_pad($newNum, 3, '0', STR_PAD_LEFT);
            }

            // If we've reached 999, move to next letter
            if ($lastLetter < 'Z') {
                $nextLetter = chr(ord($lastLetter) + 1);
                return $nextLetter . '001';
            }

            // If we've reached Z999, find the oldest unused ticket number
            // This handles the case where old tickets have been deleted/completed
            return $this->findNextAvailableTicketNumber();
        }

        // If last ticket number doesn't match expected format, start fresh
        Log::warning("Last ticket number '{$lastNumber}' doesn't match expected format (A001-Z999). Starting fresh from A001.");
        return 'A001';
    }

    /**
     * Find the next available ticket number when sequence reaches Z999.
     * Checks for the oldest unused ticket number starting from A001.
     * 
     * @return string
     */
    private function findNextAvailableTicketNumber(): string
    {
        // Get all existing ticket numbers
        $existingNumbers = Ticket::pluck('ticket_number')->toArray();
        
        // Generate all possible ticket numbers in order
        for ($letter = 'A'; $letter <= 'Z'; $letter++) {
            for ($num = 1; $num <= 999; $num++) {
                $ticketNumber = $letter . str_pad($num, 3, '0', STR_PAD_LEFT);
                
                // If this number doesn't exist, use it
                if (!in_array($ticketNumber, $existingNumbers)) {
                    Log::info("Reusing ticket number {$ticketNumber} after reaching Z999.");
                    return $ticketNumber;
                }
            }
        }

        // If all numbers are taken (shouldn't happen in practice), log warning and return A001
        Log::warning('All ticket numbers (A001-Z999) are in use. Resetting to A001. This may cause duplicates.');
        return 'A001';
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
