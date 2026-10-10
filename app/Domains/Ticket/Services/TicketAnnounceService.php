<?php

namespace App\Domains\Ticket\Services;

use App\Domains\Authentication\Models\User;
use App\Domains\Counter\Models\Counter;
use App\Domains\Device\Models\Device;
use App\Domains\Ticket\Models\OfficeAnnounceLock;
use App\Domains\Ticket\Models\PendingTicketCall;
use App\Events\TicketCalled;
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
     * @param string|null $ticketId Optional waiting ticket to call instead of FIFO next.
     * @return array{status: string, message?: string, pending_id?: string, ticket?: array, announce_id?: string}
     */
    public function requestCallNext(?string $ticketId = null): array
    {
        return TransactionHelper::execute(function () use ($ticketId) {
            $user = Auth::guard('sanctum')->user();
            if (!$user || !isset($user->id)) {
                throw new AuthenticationException('User not authenticated');
            }

            $location = $this->getUserOfficeAndRegionFromHrp();
            $officeId = (string) $location['office_id'];
            $clerkId = (string) $user->id;

            // Stale cleanup only on write path (not on TV polls).
            $this->releaseStaleLocksAndJobs($officeId);

            $existingPendingId = PendingTicketCall::query()
                ->where('office_id', $officeId)
                ->where('clerk_id', $clerkId)
                ->where('status', PendingTicketCall::STATUS_WAITING)
                ->orderBy('requested_at')
                ->value('id');

            if ($existingPendingId) {
                return [
                    'status' => 'queued',
                    'pending_id' => $existingPendingId,
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

            $ticketPayload = $ticketId
                ? $this->ticketService->callWaitingTicketForUser($user, $ticketId)
                : $this->ticketService->callNextTicketForUser($user);
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
     * Push a recall onto the office TV queue.
     *
     * "current" repeats the clerk's live ticket (called / serving / paused).
     * "previous" announces the last ticket they skipped, marked no-show, or completed.
     * If the office is already announcing, the recall waits in line like Call Next.
     *
     * @return array{status: string, message?: string, pending_id?: string, ticket?: array, announce_id?: string, recall_mode: string}
     */
    public function recall(string $mode): array
    {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['current', 'previous'], true)) {
            throw new UnprocessableEntityHttpException('Recall mode must be current or previous');
        }

        $payload = $mode === 'current'
            ? $this->ticketService->getActiveClerkTicket()
            : $this->ticketService->getPreviousClerkTicket();

        if (!$payload) {
            throw new NotFoundHttpException(
                $mode === 'current'
                    ? 'No current ticket to recall'
                    : 'No previous ticket to call'
            );
        }

        // Preempt the office lock so the TV plays this immediately instead of
        // waiting behind a stuck or already-acked announce.
        $result = $this->requestAnnounceForClaimedTicket($payload, true);
        $result['recall_mode'] = $mode;

        $ticketId = $payload['id'] ?? null;
        if ($ticketId) {
            $ticket = Ticket::query()->find($ticketId);
            if ($ticket) {
                event(new TicketCalled($ticket));
            }
        }

        return $result;
    }

    /**
     * Announce a ticket already claimed (accept transfer / resume hold).
     *
     * @param array<string, mixed> $ticketPayload
     * @param bool $preemptLock When true (Recall), replace a stuck/current lock so the TV plays now.
     * @return array{status: string, message?: string, pending_id?: string, ticket?: array, announce_id?: string}
     */
    public function requestAnnounceForClaimedTicket(array $ticketPayload, bool $preemptLock = false): array
    {
        $run = function () use ($ticketPayload, $preemptLock) {
            $user = Auth::guard('sanctum')->user();
            if (!$user || !isset($user->id)) {
                throw new AuthenticationException('User not authenticated');
            }

            $location = $this->getUserOfficeAndRegionFromHrp();
            $officeId = $this->resolveAnnounceOfficeId($ticketPayload, (string) $location['office_id']);
            $clerkId = (string) $user->id;
            $ticketId = $ticketPayload['id'] ?? null;

            $this->releaseStaleLocksAndJobs($officeId);

            $lock = $this->lockOfficeRow($officeId);

            if ($lock->is_announcing) {
                if ($preemptLock) {
                    $this->expireLockJob($lock);
                } else {
                    $pending = PendingTicketCall::create([
                        'office_id' => $officeId,
                        'clerk_id' => $clerkId,
                        'status' => PendingTicketCall::STATUS_WAITING,
                        'ticket_id' => $ticketId,
                        'requested_at' => now(),
                    ]);

                    return [
                        'status' => 'queued',
                        'pending_id' => $pending->id,
                        'ticket' => $ticketPayload,
                        'message' => 'Wait — another ticket is being announced. Yours will be called automatically.',
                    ];
                }
            }

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
        };

        if (DB::transactionLevel() === 0) {
            return TransactionHelper::execute($run);
        }

        return $run();
    }

    /**
     * Ultra-light peek for board poll — PK lock row then PK job. No writes.
     */
    public function peekPendingAnnounceForOffice(string $officeId): ?array
    {
        if ($officeId === '') {
            return null;
        }

        $lock = OfficeAnnounceLock::query()
            ->select(['current_announce_id', 'is_announcing', 'started_at'])
            ->where('office_id', $officeId)
            ->first();

        if (!$lock || !$lock->is_announcing || !$lock->current_announce_id) {
            return null;
        }

        $job = TicketAnnounceJob::query()
            ->select([
                'id',
                'ticket_number',
                'counter_name',
                'counter_type_name',
                'counter_type_code',
                'status',
            ])
            ->where('id', $lock->current_announce_id)
            ->whereIn('status', [
                TicketAnnounceJob::STATUS_PENDING,
                TicketAnnounceJob::STATUS_PLAYING,
            ])
            ->first();

        return $job?->toAnnouncePayload();
    }

    /**
     * Dedicated poll — still light: prefer lock PK, fallback to covering index.
     * No stale-release, no full transaction.
     */
    public function getPendingAnnounceForDevice(Device $device): ?array
    {
        $officeId = (string) ($device->office_id ?? '');
        if ($officeId === '') {
            throw new UnprocessableEntityHttpException('Device is not assigned to an office');
        }

        $payload = $this->peekPendingAnnounceForOffice($officeId);
        if ($payload === null) {
            // Fallback when lock row missing but a pending job exists.
            $job = TicketAnnounceJob::query()
                ->select([
                    'id',
                    'ticket_number',
                    'counter_name',
                    'counter_type_name',
                    'counter_type_code',
                    'status',
                ])
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
                TicketAnnounceJob::query()
                    ->where('id', $job->id)
                    ->where('status', TicketAnnounceJob::STATUS_PENDING)
                    ->update(['status' => TicketAnnounceJob::STATUS_PLAYING]);
            }

            return $job->toAnnouncePayload();
        }

        TicketAnnounceJob::query()
            ->where('id', $payload['announce_id'])
            ->where('status', TicketAnnounceJob::STATUS_PENDING)
            ->update(['status' => TicketAnnounceJob::STATUS_PLAYING]);

        return $payload;
    }

    /**
     * Instant peek for announce (no sleep).
     *
     * NOTE: Do not sleep/hold PHP-FPM workers here — that queues board/UI
     * requests and makes the TV feel stuck. Real push belongs on Reverb later.
     */
    public function waitPendingAnnounceForDevice(Device $device, int $timeoutSeconds = 20): ?array
    {
        // Ignore timeout — return immediately so FPM workers stay free.
        return $this->getPendingAnnounceForDevice($device);
    }

    public function acknowledgeAnnounce(Device $device, string $announceId): array
    {
        $officeId = (string) ($device->office_id ?? '');
        if ($officeId === '') {
            throw new UnprocessableEntityHttpException('Device is not assigned to an office');
        }

        return TransactionHelper::execute(function () use ($officeId, $announceId) {
            $updated = TicketAnnounceJob::query()
                ->where('id', $announceId)
                ->where('office_id', $officeId)
                ->whereIn('status', [
                    TicketAnnounceJob::STATUS_PENDING,
                    TicketAnnounceJob::STATUS_PLAYING,
                ])
                ->update(['status' => TicketAnnounceJob::STATUS_DONE]);

            if ($updated === 0) {
                $exists = TicketAnnounceJob::query()
                    ->where('id', $announceId)
                    ->where('office_id', $officeId)
                    ->exists();

                if (!$exists) {
                    throw new NotFoundHttpException('Announce job not found');
                }

                return [
                    'status' => 'already_done',
                    'announce_id' => $announceId,
                ];
            }

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

        $pending = PendingTicketCall::query()
            ->select(['id', 'status', 'ticket_id'])
            ->where('office_id', $officeId)
            ->where('clerk_id', $clerkId)
            ->whereIn('status', [
                PendingTicketCall::STATUS_WAITING,
                PendingTicketCall::STATUS_PROCESSING,
                PendingTicketCall::STATUS_DONE,
                PendingTicketCall::STATUS_CANCELLED,
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
            if ($pending->ticket_id) {
                $ticketPayload = $this->ticketService->getClaimedTicketPayloadForUser(
                    $user,
                    (string) $pending->ticket_id
                );
                if ($ticketPayload === null) {
                    $pending->update(['status' => PendingTicketCall::STATUS_CANCELLED]);
                    return $this->processNextPendingCall($officeId);
                }
            } else {
                $ticketPayload = $this->ticketService->callNextTicketForUser($user);
            }
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
            'ticket_id' => $ticketPayload['id'] ?? $pending->ticket_id,
        ]);

        return [
            'pending_id' => $pending->id,
            'clerk_id' => $pending->clerk_id,
            'announce_id' => $job->id,
            'ticket' => $ticketPayload,
        ];
    }

    /**
     * TV devices poll by device.office_id. Prefer the ticket's office so recall
     * lands on the same screens as waiting-and-serving, not only HRPD office.
     */
    private function resolveAnnounceOfficeId(array $ticketPayload, string $hrpOfficeId): string
    {
        $fromPayload = trim((string) ($ticketPayload['office_id'] ?? ''));
        if ($fromPayload !== '') {
            return $fromPayload;
        }

        $ticketId = $ticketPayload['id'] ?? null;
        if ($ticketId) {
            $fromTicket = trim((string) (Ticket::query()->where('id', $ticketId)->value('office_id') ?? ''));
            if ($fromTicket !== '') {
                return $fromTicket;
            }
        }

        return trim($hrpOfficeId);
    }

    private function expireLockJob(OfficeAnnounceLock $lock): void
    {
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
    }

    private function createAnnounceJobFromPayload(string $officeId, array $ticketPayload): TicketAnnounceJob
    {
        $counter = $ticketPayload['counter'] ?? [];
        $counterType = $counter['counter_type'] ?? [];

        return TicketAnnounceJob::create([
            'office_id' => $officeId,
            'ticket_id' => $ticketPayload['id'] ?? null,
            'ticket_number' => mb_substr((string) ($ticketPayload['ticket_number'] ?? ''), 0, 32),
            'counter_name' => isset($counter['name'])
                ? mb_substr((string) $counter['name'], 0, 32)
                : null,
            'counter_type_name' => isset($counterType['name'])
                ? mb_substr((string) $counterType['name'], 0, 32)
                : null,
            'counter_type_code' => isset($counterType['code'])
                ? mb_substr((string) $counterType['code'], 0, 24)
                : null,
            'status' => TicketAnnounceJob::STATUS_PENDING,
            'created_at' => now(),
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
                    'created_at' => now(),
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

        $staleLocks = $lockQuery->get(['office_id', 'current_announce_id']);
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

            OfficeAnnounceLock::query()
                ->where('office_id', $lock->office_id)
                ->update([
                    'is_announcing' => false,
                    'current_announce_id' => null,
                    'started_at' => null,
                ]);

            try {
                $this->processNextPendingCall((string) $lock->office_id);
            } catch (\Throwable $e) {
                Log::warning('Failed to drain pending call after stale lock release', [
                    'office_id' => $lock->office_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
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
