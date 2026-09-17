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
     * Always claim the ticket for the clerk, then enqueue TV audio.
     * Clerks are never blocked on "queued" — TV serializes playback from jobs.
     *
     * @return array{status: string, ticket: array, announce_id?: string|null}
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

        $this->releaseDeadAnnounceState($officeId);
        $this->clearClerkWaitingPendings($clerkId, $officeId);

        $active = $this->ticketService->getActiveClerkTicket();
        if ($active) {
            $job = $this->enqueueAnnounceJob($officeId, $active);

            return [
                'status' => 'called',
                'ticket' => $active,
                'announce_id' => $job->id,
            ];
        }

        $ticketPayload = $ticketId
            ? $this->ticketService->callWaitingTicketForUser($user, $ticketId)
            : $this->ticketService->callNextTicketForUser($user);

        $job = $this->enqueueAnnounceJob($officeId, $ticketPayload);

        return [
            'status' => 'called',
            'ticket' => $ticketPayload,
            'announce_id' => $job->id,
        ];
    }

    /**
     * @param array<string, mixed> $ticketPayload
     * @return array{status: string, ticket?: array, announce_id?: string}
     */
    public function requestAnnounceForClaimedTicket(array $ticketPayload): array
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user || !isset($user->id)) {
            throw new AuthenticationException('User not authenticated');
        }

        $location = $this->getUserOfficeAndRegionFromHrp();
        $officeId = (string) $location['office_id'];

        $this->releaseDeadAnnounceState($officeId);
        $job = $this->enqueueAnnounceJob($officeId, $ticketPayload);

        return [
            'status' => 'called',
            'ticket' => $ticketPayload,
            'announce_id' => $job->id,
        ];
    }

    public function peekPendingAnnounceForOffice(string $officeId): ?array
    {
        if ($officeId === '') {
            return null;
        }

        $job = $this->nextPlayableJob($officeId);

        return $job?->toAnnouncePayload();
    }

    public function getPendingAnnounceForDevice(Device $device): ?array
    {
        $officeId = (string) ($device->office_id ?? '');
        if ($officeId === '') {
            throw new UnprocessableEntityHttpException('Device is not assigned to an office');
        }

        $job = $this->nextPlayableJob($officeId);
        if (!$job) {
            return null;
        }

        if ($job->status === TicketAnnounceJob::STATUS_PENDING) {
            TicketAnnounceJob::query()
                ->where('id', $job->id)
                ->where('status', TicketAnnounceJob::STATUS_PENDING)
                ->update(['status' => TicketAnnounceJob::STATUS_PLAYING]);
        }

        $this->pointLockAtJob($officeId, $job);

        return $job->toAnnouncePayload();
    }

    public function waitPendingAnnounceForDevice(Device $device, int $timeoutSeconds = 20): ?array
    {
        return $this->getPendingAnnounceForDevice($device);
    }

    public function acknowledgeAnnounce(Device $device, string $announceId): array
    {
        $officeId = (string) ($device->office_id ?? '');
        if ($officeId === '') {
            throw new UnprocessableEntityHttpException('Device is not assigned to an office');
        }

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

            $this->promoteNextAnnounceJob($officeId, $announceId);

            return [
                'status' => 'already_done',
                'announce_id' => $announceId,
            ];
        }

        $this->promoteNextAnnounceJob($officeId, $announceId);

        return [
            'status' => 'acked',
            'announce_id' => $announceId,
        ];
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

        $active = $this->ticketService->getActiveClerkTicket();
        if ($active) {
            return [
                'status' => 'called',
                'ticket' => $active,
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

    /**
     * @param array<string, mixed> $ticketPayload
     */
    private function enqueueAnnounceJob(string $officeId, array $ticketPayload): TicketAnnounceJob
    {
        $ticketId = $ticketPayload['id'] ?? null;
        if ($ticketId) {
            $existing = TicketAnnounceJob::query()
                ->where('office_id', $officeId)
                ->where('ticket_id', $ticketId)
                ->whereIn('status', [
                    TicketAnnounceJob::STATUS_PENDING,
                    TicketAnnounceJob::STATUS_PLAYING,
                ])
                ->orderBy('created_at')
                ->first();
            if ($existing) {
                $this->pointLockAtJob($officeId, $existing);

                return $existing;
            }
        }

        $job = $this->createAnnounceJobFromPayload($officeId, $ticketPayload);
        $this->pointLockAtJob($officeId, $job);

        return $job;
    }

    private function nextPlayableJob(string $officeId): ?TicketAnnounceJob
    {
        $this->releaseDeadAnnounceState($officeId);

        $lock = OfficeAnnounceLock::query()
            ->where('office_id', $officeId)
            ->first();

        if ($lock && $lock->is_announcing && $lock->current_announce_id) {
            $current = TicketAnnounceJob::query()
                ->where('id', $lock->current_announce_id)
                ->where('office_id', $officeId)
                ->whereIn('status', [
                    TicketAnnounceJob::STATUS_PENDING,
                    TicketAnnounceJob::STATUS_PLAYING,
                ])
                ->first();
            if ($current) {
                return $current;
            }
        }

        return TicketAnnounceJob::query()
            ->where('office_id', $officeId)
            ->whereIn('status', [
                TicketAnnounceJob::STATUS_PENDING,
                TicketAnnounceJob::STATUS_PLAYING,
            ])
            ->orderBy('created_at')
            ->first();
    }

    private function pointLockAtJob(string $officeId, TicketAnnounceJob $job): void
    {
        TransactionHelper::execute(function () use ($officeId, $job) {
            $lock = OfficeAnnounceLock::query()
                ->where('office_id', $officeId)
                ->lockForUpdate()
                ->first();

            if (!$lock) {
                OfficeAnnounceLock::query()->create([
                    'office_id' => $officeId,
                    'is_announcing' => true,
                    'current_announce_id' => $job->id,
                    'started_at' => now(),
                    'created_at' => now(),
                ]);

                return;
            }

            $liveId = $lock->is_announcing ? $lock->current_announce_id : null;
            if ($liveId) {
                $live = TicketAnnounceJob::query()
                    ->where('id', $liveId)
                    ->whereIn('status', [
                        TicketAnnounceJob::STATUS_PENDING,
                        TicketAnnounceJob::STATUS_PLAYING,
                    ])
                    ->exists();
                if ($live && (string) $liveId !== (string) $job->id) {
                    return;
                }
            }

            $lock->update([
                'is_announcing' => true,
                'current_announce_id' => $job->id,
                'started_at' => now(),
            ]);
        });
    }

    private function promoteNextAnnounceJob(string $officeId, string $finishedAnnounceId): void
    {
        TransactionHelper::execute(function () use ($officeId, $finishedAnnounceId) {
            $lock = OfficeAnnounceLock::query()
                ->where('office_id', $officeId)
                ->lockForUpdate()
                ->first();

            $next = TicketAnnounceJob::query()
                ->where('office_id', $officeId)
                ->whereIn('status', [
                    TicketAnnounceJob::STATUS_PENDING,
                    TicketAnnounceJob::STATUS_PLAYING,
                ])
                ->orderBy('created_at')
                ->first();

            if (!$lock) {
                if ($next) {
                    OfficeAnnounceLock::query()->create([
                        'office_id' => $officeId,
                        'is_announcing' => true,
                        'current_announce_id' => $next->id,
                        'started_at' => now(),
                        'created_at' => now(),
                    ]);
                }

                return;
            }

            $pointsAtFinished = (string) $lock->current_announce_id === (string) $finishedAnnounceId;
            if (!$pointsAtFinished && $lock->is_announcing && $lock->current_announce_id) {
                $stillLive = TicketAnnounceJob::query()
                    ->where('id', $lock->current_announce_id)
                    ->whereIn('status', [
                        TicketAnnounceJob::STATUS_PENDING,
                        TicketAnnounceJob::STATUS_PLAYING,
                    ])
                    ->exists();
                if ($stillLive) {
                    return;
                }
            }

            if ($next) {
                $lock->update([
                    'is_announcing' => true,
                    'current_announce_id' => $next->id,
                    'started_at' => now(),
                ]);

                return;
            }

            $lock->update([
                'is_announcing' => false,
                'current_announce_id' => null,
                'started_at' => null,
            ]);
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

    private function clearClerkWaitingPendings(string $clerkId, string $officeId): void
    {
        PendingTicketCall::query()
            ->where('clerk_id', $clerkId)
            ->where('office_id', $officeId)
            ->whereIn('status', [
                PendingTicketCall::STATUS_WAITING,
                PendingTicketCall::STATUS_PROCESSING,
            ])
            ->update(['status' => PendingTicketCall::STATUS_CANCELLED]);
    }

    /**
     * Clear locks that claim to be announcing when nothing is playable.
     */
    public function releaseDeadAnnounceState(string $officeId): void
    {
        $lock = OfficeAnnounceLock::query()->where('office_id', $officeId)->first();
        if (!$lock || !$lock->is_announcing) {
            return;
        }

        $jobLive = false;
        if ($lock->current_announce_id) {
            $jobLive = TicketAnnounceJob::query()
                ->where('id', $lock->current_announce_id)
                ->whereIn('status', [
                    TicketAnnounceJob::STATUS_PENDING,
                    TicketAnnounceJob::STATUS_PLAYING,
                ])
                ->exists();
        }

        $staleTime = $lock->started_at
            && $lock->started_at->lt(now()->subSeconds(self::LOCK_TTL_SECONDS));

        if ($jobLive && !$staleTime) {
            return;
        }

        if ($lock->current_announce_id && ($staleTime || !$jobLive)) {
            TicketAnnounceJob::query()
                ->where('id', $lock->current_announce_id)
                ->whereIn('status', [
                    TicketAnnounceJob::STATUS_PENDING,
                    TicketAnnounceJob::STATUS_PLAYING,
                ])
                ->update(['status' => TicketAnnounceJob::STATUS_EXPIRED]);
        }

        OfficeAnnounceLock::query()
            ->where('office_id', $officeId)
            ->update([
                'is_announcing' => false,
                'current_announce_id' => null,
                'started_at' => null,
            ]);
    }

    public function releaseStaleLocksAndJobs(?string $officeId = null): void
    {
        if ($officeId !== null) {
            $this->releaseDeadAnnounceState($officeId);

            return;
        }

        $officeIds = OfficeAnnounceLock::query()
            ->where('is_announcing', true)
            ->pluck('office_id');

        foreach ($officeIds as $id) {
            $this->releaseDeadAnnounceState((string) $id);
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
