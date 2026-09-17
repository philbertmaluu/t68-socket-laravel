<?php

namespace App\Domains\Ticket\Services;

use App\Domains\Authentication\Models\User;
use App\Domains\Counter\Models\Counter;
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
     * @param string|null $ticketId Optional waiting ticket to call instead of FIFO next.
     * @return array{status: string, message?: string, pending_id?: string, ticket?: array, announce_id?: string}
     */
    public function requestCallNext(?string $ticketId = null): array
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user || !isset($user->id)) {
            throw new AuthenticationException('User not authenticated');
        }

        $location = $this->getUserOfficeAndRegionFromHrp();
        $officeId = (string) $location['office_id'];
        $clerkId = (string) $user->id;

        // Outside the announce-lock TX — live Oracle waits here used to block call-next for ~60s.
        $this->releaseStaleLocksAndJobs($officeId);

        $gate = TransactionHelper::execute(function () use ($officeId, $clerkId) {
            $existingPendingId = PendingTicketCall::query()
                ->where('office_id', $officeId)
                ->where('clerk_id', $clerkId)
                ->where('status', PendingTicketCall::STATUS_WAITING)
                ->orderBy('requested_at')
                ->value('id');

            if ($existingPendingId) {
                return $this->queuedPayload((string) $existingPendingId);
            }

            $lock = $this->tryLockOfficeRow($officeId);
            if ($lock === null || $lock->is_announcing) {
                $pending = PendingTicketCall::create([
                    'office_id' => $officeId,
                    'clerk_id' => $clerkId,
                    'status' => PendingTicketCall::STATUS_WAITING,
                    'requested_at' => now(),
                ]);

                return $this->queuedPayload((string) $pending->id);
            }

            return ['status' => 'slot'];
        });

        if (($gate['status'] ?? '') === 'queued') {
            return $gate;
        }

        $ticketPayload = $ticketId
            ? $this->ticketService->callWaitingTicketForUser($user, $ticketId)
            : $this->ticketService->callNextTicketForUser($user);

        return TransactionHelper::execute(function () use ($officeId, $clerkId, $ticketPayload) {
            $lock = $this->tryLockOfficeRow($officeId);
            if ($lock === null || $lock->is_announcing) {
                PendingTicketCall::create([
                    'office_id' => $officeId,
                    'clerk_id' => $clerkId,
                    'status' => PendingTicketCall::STATUS_WAITING,
                    'ticket_id' => $ticketPayload['id'] ?? null,
                    'requested_at' => now(),
                ]);

                return [
                    'status' => 'called',
                    'ticket' => $ticketPayload,
                    'announce_id' => null,
                ];
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
        });
    }

    /**
     * Announce a ticket already claimed (accept transfer / resume hold).
     *
     * @param array<string, mixed> $ticketPayload
     * @return array{status: string, message?: string, pending_id?: string, ticket?: array, announce_id?: string}
     */
    public function requestAnnounceForClaimedTicket(array $ticketPayload): array
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user || !isset($user->id)) {
            throw new AuthenticationException('User not authenticated');
        }

        $location = $this->getUserOfficeAndRegionFromHrp();
        $officeId = (string) $location['office_id'];
        $clerkId = (string) $user->id;
        $ticketId = $ticketPayload['id'] ?? null;

        $this->releaseStaleLocksAndJobs($officeId);

        return TransactionHelper::execute(function () use ($officeId, $clerkId, $ticketId, $ticketPayload) {
            $lock = $this->tryLockOfficeRow($officeId);

            if ($lock === null || $lock->is_announcing) {
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

        // Soft TTL check without write — skip stale ids quickly.
        if ($lock->started_at && $lock->started_at->lt(now()->subSeconds(self::LOCK_TTL_SECONDS))) {
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

        $result = TransactionHelper::execute(function () use ($officeId, $announceId) {
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

            $lock = $this->tryLockOfficeRow($officeId);
            if ($lock && ((string) $lock->current_announce_id === (string) $announceId || $lock->is_announcing)) {
                $lock->update([
                    'is_announcing' => false,
                    'current_announce_id' => null,
                    'started_at' => null,
                ]);
            }

            return [
                'status' => 'acked',
                'announce_id' => $announceId,
            ];
        });

        $autoCalled = null;
        try {
            $autoCalled = $this->processNextPendingCall($officeId);
        } catch (\Throwable $e) {
            Log::warning('Failed to drain pending call after announce ack', [
                'office_id' => $officeId,
                'error' => $e->getMessage(),
            ]);
        }

        $result['auto_called'] = $autoCalled;

        return $result;
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
        $claimed = TransactionHelper::execute(function () use ($officeId) {
            $pending = $this->firstWithNowaitLock(
                PendingTicketCall::query()
                    ->where('office_id', $officeId)
                    ->where('status', PendingTicketCall::STATUS_WAITING)
                    ->orderBy('requested_at')
            );

            if (!$pending) {
                return null;
            }

            $user = User::query()->find($pending->clerk_id);
            if (!$user) {
                $pending->update(['status' => PendingTicketCall::STATUS_CANCELLED]);
                return ['retry' => true];
            }

            $pending->update(['status' => PendingTicketCall::STATUS_PROCESSING]);

            return ['pending' => $pending, 'user' => $user];
        });

        if ($claimed === null) {
            return null;
        }
        if (!empty($claimed['retry'])) {
            return $this->processNextPendingCall($officeId);
        }

        /** @var PendingTicketCall $pending */
        $pending = $claimed['pending'];
        /** @var User $user */
        $user = $claimed['user'];

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

        return TransactionHelper::execute(function () use ($officeId, $pending, $ticketPayload) {
            $lock = $this->tryLockOfficeRow($officeId);
            if ($lock === null || $lock->is_announcing) {
                $pending->update([
                    'status' => PendingTicketCall::STATUS_WAITING,
                    'ticket_id' => $ticketPayload['id'] ?? $pending->ticket_id,
                ]);

                return null;
            }

            $job = $this->createAnnounceJobFromPayload($officeId, $ticketPayload);
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
        });
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

    /**
     * @return array{status: string, pending_id: string, message: string}
     */
    private function queuedPayload(string $pendingId): array
    {
        return [
            'status' => 'queued',
            'pending_id' => $pendingId,
            'message' => 'Wait — another ticket is being announced. Yours will be called automatically.',
        ];
    }

    /**
     * Non-blocking office lock. Null means another session holds the row —
     * treat as busy so clerks are not stuck until the HTTP client aborts.
     */
    private function tryLockOfficeRow(string $officeId): ?OfficeAnnounceLock
    {
        $lock = $this->firstWithNowaitLock(
            OfficeAnnounceLock::query()->where('office_id', $officeId)
        );

        if ($lock) {
            return $lock;
        }

        $exists = OfficeAnnounceLock::query()->where('office_id', $officeId)->exists();
        if ($exists) {
            return null;
        }

        OfficeAnnounceLock::query()->firstOrCreate(
            ['office_id' => $officeId],
            [
                'is_announcing' => false,
                'current_announce_id' => null,
                'started_at' => null,
                'created_at' => now(),
            ]
        );

        return $this->firstWithNowaitLock(
            OfficeAnnounceLock::query()->where('office_id', $officeId)
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function firstWithNowaitLock($query)
    {
        try {
            return $query->lock('for update nowait')->first();
        } catch (\Throwable $e) {
            if ($this->isRowBusyLockError($e)) {
                return null;
            }
            throw $e;
        }
    }

    private function isRowBusyLockError(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'ORA-00054')
            || str_contains($message, 'NOWAIT')
            || str_contains($message, '55P03')
            || str_contains(strtolower($message), 'could not obtain lock');
    }

    private function lockOfficeRow(string $officeId): OfficeAnnounceLock
    {
        $lock = $this->tryLockOfficeRow($officeId);
        if ($lock) {
            return $lock;
        }

        throw new UnprocessableEntityHttpException('Office announce lock is busy');
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
