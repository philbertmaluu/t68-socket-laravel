<?php

namespace App\Domains\Ticket\Services;

use App\Domains\Authentication\Models\User;
use App\Domains\Counter\Models\Counter;
use App\Domains\Counter\Models\CounterClerk;
use App\Domains\Device\Models\Device;
use App\Domains\Ticket\Models\OfficeAnnounceLock;
use App\Domains\Ticket\Models\PendingTicketCall;
use App\Domains\Ticket\Models\Ticket;
use App\Domains\Ticket\Models\TicketAnnounceJob;
use App\Shared\Helpers\TransactionHelper;
use App\Traits\UserOfficeTrait;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class TicketAnnounceService
{
    use UserOfficeTrait;

    public const LOCK_TTL_SECONDS = 60;
    public const PENDING_TTL_MINUTES = 10;

    private TicketService $ticketService;

    public function __construct(?TicketService $ticketService = null)
    {
        $this->ticketService = $ticketService ?? new TicketService();
    }

    /**
     * Call next with office announce serialization.
     *
     * @return array{status: string, message?: string, pending_id?: string, ticket?: array}
     */
    public function requestCallNext(): array
    {
        return TransactionHelper::execute(function () {
            $user = Auth::guard('sanctum')->user();
            if (!$user || !isset($user->id)) {
                throw new AuthenticationException('User not authenticated');
            }

            $location = $this->getUserOfficeAndRegionFromHrp();
            $officeId = (string) $location['office_id'];
            $clerkId = (string) $user->id;

            $this->releaseStaleLocksAndJobs($officeId);
            $this->expireStalePendingCalls($officeId);

            $existingPending = PendingTicketCall::query()
                ->where('office_id', $officeId)
                ->where('clerk_id', $clerkId)
                ->where('status', PendingTicketCall::STATUS_WAITING)
                ->orderBy('requested_at')
                ->first();

            if ($existingPending) {
                return [
                    'status' => 'queued',
                    'pending_id' => $existingPending->id,
                    'message' => 'Wait — another ticket is being announced. Yours will be called automatically.',
                ];
            }

            $lock = $this->lockOfficeRow($officeId);

            if ($lock->is_announcing) {
                $pending = PendingTicketCall::create([
                    'office_id' => $officeId,
                    'clerk_id' => $clerkId,
                    'status' => PendingTicketCall::STATUS_WAITING,
                    'requested_at' => now(),
                ]);

                return [
                    'status' => 'queued',
                    'pending_id' => $pending->id,
                    'message' => 'Wait — another ticket is being announced. Yours will be called automatically.',
                ];
            }

            $ticketPayload = $this->ticketService->callNextTicketForUser($user);
            $job = $this->createAnnounceJobFromPayload($officeId, $ticketPayload);

            $lock->update([
                'is_announcing' => true,
                'current_announce_id' => $job->id,
                'started_at' => now(),
            ]);

            return [
                'status' => 'called',
                'ticket' => $ticketPayload,
                'announce_id' => $job->id,
            ];
        });
    }

    /**
     * Next pending/playing announce job for the device office.
     */
    public function getPendingAnnounceForDevice(Device $device): ?array
    {
        $officeId = (string) ($device->office_id ?? '');
        if ($officeId === '') {
            throw new UnprocessableEntityHttpException('Device is not assigned to an office');
        }

        return TransactionHelper::execute(function () use ($officeId) {
            $this->releaseStaleLocksAndJobs($officeId);

            $job = TicketAnnounceJob::query()
                ->where('office_id', $officeId)
                ->whereIn('status', [
                    TicketAnnounceJob::STATUS_PENDING,
                    TicketAnnounceJob::STATUS_PLAYING,
                ])
                ->orderBy('created_at')
                ->first();

            if (!$job) {
                return null;
            }

            if ($job->status === TicketAnnounceJob::STATUS_PENDING) {
                $job->update(['status' => TicketAnnounceJob::STATUS_PLAYING]);
            }

            return $job->toAnnouncePayload();
        });
    }

    /**
     * TV finished playing an announce job.
     */
    public function acknowledgeAnnounce(Device $device, string $announceId): array
    {
        $officeId = (string) ($device->office_id ?? '');
        if ($officeId === '') {
            throw new UnprocessableEntityHttpException('Device is not assigned to an office');
        }

        return TransactionHelper::execute(function () use ($officeId, $announceId) {
            $this->releaseStaleLocksAndJobs($officeId);

            $job = TicketAnnounceJob::query()
                ->where('id', $announceId)
                ->where('office_id', $officeId)
                ->first();

            if (!$job) {
                throw new NotFoundHttpException('Announce job not found');
            }

            if (in_array($job->status, [TicketAnnounceJob::STATUS_DONE, TicketAnnounceJob::STATUS_EXPIRED], true)) {
                return [
                    'status' => 'already_done',
                    'announce_id' => $job->id,
                ];
            }

            $job->update(['status' => TicketAnnounceJob::STATUS_DONE]);

            $lock = $this->lockOfficeRow($officeId);
            if ((string) $lock->current_announce_id === (string) $announceId || $lock->is_announcing) {
                $lock->update([
                    'is_announcing' => false,
                    'current_announce_id' => null,
                    'started_at' => null,
                ]);
            }

            $autoCalled = $this->processNextPendingCall($officeId);

            return [
                'status' => 'acked',
                'announce_id' => $announceId,
                'auto_called' => $autoCalled,
            ];
        });
    }

    /**
     * Clerk polls while waiting in the announce queue.
     *
     * @return array{status: string, pending_id?: string, message?: string, ticket?: array}
     */
    public function getMyPendingCall(): array
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user || !isset($user->id)) {
            throw new AuthenticationException('User not authenticated');
        }

        $location = $this->getUserOfficeAndRegionFromHrp();
        $officeId = (string) $location['office_id'];
        $clerkId = (string) $user->id;

        $this->releaseStaleLocksAndJobs($officeId);
        $this->expireStalePendingCalls($officeId);

        $pending = PendingTicketCall::query()
            ->where('office_id', $officeId)
            ->where('clerk_id', $clerkId)
            ->whereIn('status', [
                PendingTicketCall::STATUS_WAITING,
                PendingTicketCall::STATUS_PROCESSING,
                PendingTicketCall::STATUS_DONE,
            ])
            ->orderByDesc('requested_at')
            ->first();

        if ($pending && $pending->status === PendingTicketCall::STATUS_DONE && $pending->ticket_id) {
            $ticket = Ticket::query()->find($pending->ticket_id);
            if ($ticket) {
                $counter = $ticket->counter_id
                    ? Counter::query()->with('counterType')->find($ticket->counter_id)
                    : null;
                return [
                    'status' => 'called',
                    'pending_id' => $pending->id,
                    'ticket' => $this->ticketService->formatClerkTicketPayloadPublic($ticket, $counter),
                ];
            }
        }

        if ($pending && in_array($pending->status, [
            PendingTicketCall::STATUS_WAITING,
            PendingTicketCall::STATUS_PROCESSING,
        ], true)) {
            return [
                'status' => 'waiting',
                'pending_id' => $pending->id,
                'message' => 'Wait — another ticket is being announced. Yours will be called automatically.',
            ];
        }

        $active = $this->ticketService->getActiveClerkTicket();
        if ($active) {
            return [
                'status' => 'called',
                'ticket' => $active,
            ];
        }

        if ($pending && $pending->status === PendingTicketCall::STATUS_CANCELLED) {
            return [
                'status' => 'cancelled',
                'pending_id' => $pending->id,
                'message' => 'Your queued call was cancelled.',
            ];
        }

        return [
            'status' => 'idle',
        ];
    }

    /**
     * Cancel waiting pending calls for the authenticated clerk.
     */
    public function cancelMyPendingCalls(): int
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user || !isset($user->id)) {
            throw new AuthenticationException('User not authenticated');
        }

        return PendingTicketCall::query()
            ->where('clerk_id', (string) $user->id)
            ->where('status', PendingTicketCall::STATUS_WAITING)
            ->update(['status' => PendingTicketCall::STATUS_CANCELLED]);
    }

    private function processNextPendingCall(string $officeId): ?array
    {
        if (DB::transactionLevel() === 0) {
            return TransactionHelper::execute(fn () => $this->processNextPendingCall($officeId));
        }

        $pending = PendingTicketCall::query()
            ->where('office_id', $officeId)
            ->where('status', PendingTicketCall::STATUS_WAITING)
            ->orderBy('requested_at')
            ->lockForUpdate()
            ->first();

        if (!$pending) {
            return null;
        }

        $pending->update(['status' => PendingTicketCall::STATUS_PROCESSING]);

        $user = User::query()->find($pending->clerk_id);
        if (!$user) {
            $pending->update(['status' => PendingTicketCall::STATUS_CANCELLED]);
            return $this->processNextPendingCall($officeId);
        }

        try {
            $ticketPayload = $this->ticketService->callNextTicketForUser($user);
        } catch (\Throwable $e) {
            Log::warning('Auto call-next failed for pending clerk', [
                'clerk_id' => $pending->clerk_id,
                'office_id' => $officeId,
                'error' => $e->getMessage(),
            ]);
            $pending->update(['status' => PendingTicketCall::STATUS_CANCELLED]);
            return $this->processNextPendingCall($officeId);
        }

        $job = $this->createAnnounceJobFromPayload($officeId, $ticketPayload);
        $lock = $this->lockOfficeRow($officeId);
        $lock->update([
            'is_announcing' => true,
            'current_announce_id' => $job->id,
            'started_at' => now(),
        ]);

        $pending->update([
            'status' => PendingTicketCall::STATUS_DONE,
            'ticket_id' => $ticketPayload['id'] ?? null,
        ]);

        return [
            'pending_id' => $pending->id,
            'clerk_id' => $pending->clerk_id,
            'announce_id' => $job->id,
            'ticket' => $ticketPayload,
        ];
    }

    private function createAnnounceJobFromPayload(string $officeId, array $ticketPayload): TicketAnnounceJob
    {
        $counter = $ticketPayload['counter'] ?? [];
        $counterType = $counter['counter_type'] ?? [];

        return TicketAnnounceJob::create([
            'office_id' => $officeId,
            'ticket_id' => $ticketPayload['id'] ?? null,
            'ticket_number' => (string) ($ticketPayload['ticket_number'] ?? ''),
            'counter_name' => isset($counter['name']) ? (string) $counter['name'] : null,
            'counter_type_name' => isset($counterType['name']) ? (string) $counterType['name'] : null,
            'counter_type_code' => isset($counterType['code']) ? (string) $counterType['code'] : null,
            'status' => TicketAnnounceJob::STATUS_PENDING,
        ]);
    }

    private function lockOfficeRow(string $officeId): OfficeAnnounceLock
    {
        $lock = OfficeAnnounceLock::query()
            ->where('office_id', $officeId)
            ->lockForUpdate()
            ->first();

        if (!$lock) {
            OfficeAnnounceLock::query()->firstOrCreate(
                ['office_id' => $officeId],
                [
                    'is_announcing' => false,
                    'current_announce_id' => null,
                    'started_at' => null,
                ]
            );

            $lock = OfficeAnnounceLock::query()
                ->where('office_id', $officeId)
                ->lockForUpdate()
                ->first();
        }

        return $lock;
    }

    public function releaseStaleLocksAndJobs(?string $officeId = null): void
    {
        $cutoff = now()->subSeconds(self::LOCK_TTL_SECONDS);

        $lockQuery = OfficeAnnounceLock::query()
            ->where('is_announcing', true)
            ->whereNotNull('started_at')
            ->where('started_at', '<', $cutoff);

        if ($officeId !== null) {
            $lockQuery->where('office_id', $officeId);
        }

        $staleLocks = $lockQuery->get();
        foreach ($staleLocks as $lock) {
            if ($lock->current_announce_id) {
                TicketAnnounceJob::query()
                    ->where('id', $lock->current_announce_id)
                    ->whereIn('status', [
                        TicketAnnounceJob::STATUS_PENDING,
                        TicketAnnounceJob::STATUS_PLAYING,
                    ])
                    ->update(['status' => TicketAnnounceJob::STATUS_EXPIRED]);
            }

            $lock->update([
                'is_announcing' => false,
                'current_announce_id' => null,
                'started_at' => null,
            ]);

            // Free the speaker slot so the next waiting clerk can be auto-called.
            try {
                $this->processNextPendingCall((string) $lock->office_id);
            } catch (\Throwable $e) {
                Log::warning('Failed to drain pending call after stale lock release', [
                    'office_id' => $lock->office_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $jobQuery = TicketAnnounceJob::query()
            ->whereIn('status', [
                TicketAnnounceJob::STATUS_PENDING,
                TicketAnnounceJob::STATUS_PLAYING,
            ])
            ->where('created_at', '<', $cutoff);

        if ($officeId !== null) {
            $jobQuery->where('office_id', $officeId);
        }

        $jobQuery->update(['status' => TicketAnnounceJob::STATUS_EXPIRED]);
    }

    public function expireStalePendingCalls(?string $officeId = null): void
    {
        $cutoff = now()->subMinutes(self::PENDING_TTL_MINUTES);

        $query = PendingTicketCall::query()
            ->where('status', PendingTicketCall::STATUS_WAITING)
            ->where('requested_at', '<', $cutoff);

        if ($officeId !== null) {
            $query->where('office_id', $officeId);
        }

        $query->update(['status' => PendingTicketCall::STATUS_CANCELLED]);
    }
}
