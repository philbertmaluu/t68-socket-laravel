<?php

namespace App\Domains\Mood\Services;

use App\Domains\Counter\Models\Counter;
use App\Domains\Device\Models\Device;
use App\Domains\Mood\Models\MoodFeedbackSession;
use App\Domains\Ticket\Models\Ticket;
use App\Events\MoodFeedbackRequested;
use App\Shared\Helpers\TransactionHelper;
use Illuminate\Support\Str;

class MoodFeedbackSessionService
{
    public function createForTicket(Ticket $ticket): ?MoodFeedbackSession
    {
        if (empty($ticket->counter_id)) {
            return null;
        }

        $device = Device::withoutGlobalScope('tenant')
            ->where('type', Device::TYPE_MOOD_CHECKER)
            ->where('mood_mode', Device::MOOD_MODE_COUNTER)
            ->where('counter_id', (string) $ticket->counter_id)
            ->where('tenant_id', $ticket->tenant_id)
            ->where('status', '!=', Device::STATUS_MAINTENANCE)
            ->first();

        if (!$device) {
            return null;
        }

        $this->expireActiveSessionsForDevice($device);

        $sessionTimeout = 30;

        $session = MoodFeedbackSession::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'counter_id' => $ticket->counter_id ? (string) $ticket->counter_id : null,
            'officer_id' => $ticket->clerk_id ? (string) $ticket->clerk_id : null,
            'branch_id' => $ticket->office_id ? (string) $ticket->office_id : null,
            'device_id' => (string) $device->id,
            'service_id' => $ticket->service_type ? (string) $ticket->service_type : null,
            'customer_type' => $ticket->member_number ? 'member' : 'visitor',
            'start_time' => now(),
            'expire_time' => now()->addSeconds($sessionTimeout),
            'status' => MoodFeedbackSession::STATUS_PENDING,
        ]);

        event(new MoodFeedbackRequested($session));

        return $session;
    }

    public function getPendingSession(Device $device): ?MoodFeedbackSession
    {
        $session = MoodFeedbackSession::where('device_id', $device->id)
            ->whereIn('status', [MoodFeedbackSession::STATUS_PENDING, MoodFeedbackSession::STATUS_ACTIVE])
            ->orderByDesc('start_time')
            ->first();

        if (!$session) {
            return null;
        }

        if ($session->isExpired()) {
            $this->expireSession($session);

            return null;
        }

        if ($session->status === MoodFeedbackSession::STATUS_PENDING) {
            $session->update(['status' => MoodFeedbackSession::STATUS_ACTIVE]);
        }

        return $session->fresh();
    }

    /**
     * Current called/serving ticket for this mood device's counter.
     *
     * @return array{ticket_id: string, ticket_number: string, status: string, counter_id: string, counter_name: string|null}|null
     */
    public function getCurrentServingTicket(Device $device): ?array
    {
        $counterId = trim((string) ($device->counter_id ?? ''));
        if ($counterId === '') {
            return null;
        }

        $counterName = Counter::query()
            ->where('id', $counterId)
            ->value('name');

        $ticket = Ticket::query()
            ->where('counter_id', $counterId)
            ->whereIn('status', ['called', 'serving'])
            ->orderByRaw("CASE status WHEN 'serving' THEN 0 WHEN 'called' THEN 1 ELSE 2 END")
            ->orderByDesc('called_at')
            ->orderByDesc('updated_at')
            ->first();

        if (!$ticket) {
            return null;
        }

        return [
            'ticket_id' => (string) $ticket->id,
            'ticket_number' => (string) $ticket->ticket_number,
            'status' => (string) $ticket->status,
            'counter_id' => $counterId,
            'counter_name' => $counterName ? (string) $counterName : null,
        ];
    }

    public function expireSession(MoodFeedbackSession $session): MoodFeedbackSession
    {
        if ($session->status === MoodFeedbackSession::STATUS_EXPIRED) {
            return $session;
        }

        $session->update(['status' => MoodFeedbackSession::STATUS_EXPIRED]);

        return $session->fresh();
    }

    public function completeSession(MoodFeedbackSession $session): MoodFeedbackSession
    {
        $session->update(['status' => MoodFeedbackSession::STATUS_COMPLETED]);

        return $session->fresh();
    }

    public function expireActiveSessionsForDevice(Device $device): void
    {
        MoodFeedbackSession::where('device_id', $device->id)
            ->whereIn('status', [MoodFeedbackSession::STATUS_PENDING, MoodFeedbackSession::STATUS_ACTIVE])
            ->update(['status' => MoodFeedbackSession::STATUS_EXPIRED]);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatSession(MoodFeedbackSession $session): array
    {
        return [
            'session_id' => $session->id,
            'ticket_id' => $session->ticket_id,
            'counter_id' => $session->counter_id,
            'officer_id' => $session->officer_id,
            'branch_id' => $session->branch_id,
            'device_id' => $session->device_id,
            'service_id' => $session->service_id,
            'customer_type' => $session->customer_type,
            'start_time' => $session->start_time?->toIso8601String(),
            'expire_time' => $session->expire_time?->toIso8601String(),
            'status' => $session->status,
        ];
    }
}
