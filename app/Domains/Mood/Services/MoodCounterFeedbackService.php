<?php

namespace App\Domains\Mood\Services;

use App\Domains\Device\Models\Device;
use App\Domains\Mood\Models\MoodCounterFeedback;
use App\Domains\Mood\Models\MoodFeedbackSession;
use App\Domains\Mood\Models\MoodRatingOption;
use App\Shared\Helpers\TransactionHelper;
use Illuminate\Support\Str;

class MoodCounterFeedbackService
{
    private MoodFeedbackSessionService $sessionService;

    public function __construct()
    {
        $this->sessionService = new MoodFeedbackSessionService();
    }

    public function submit(Device $device, array $data): MoodCounterFeedback
    {
        if (!$device->isMoodCounterMode()) {
            throw new \RuntimeException('Device is not in counter feedback mode');
        }

        $clientUuid = (string) ($data['client_uuid'] ?? Str::uuid()->toString());

        $existing = MoodCounterFeedback::where('client_uuid', $clientUuid)->first();
        if ($existing) {
            return $existing;
        }

        $sessionId = (string) ($data['session_id'] ?? '');
        $session = null;

        if ($sessionId !== '') {
            $session = MoodFeedbackSession::where('id', $sessionId)
                ->where('device_id', $device->id)
                ->first();
        } else {
            $session = $this->sessionService->getPendingSession($device);
        }

        if (!$session || !$session->isActive()) {
            throw new \RuntimeException('No active feedback session found');
        }

        $rating = $this->resolveRating($device, $data);

        return TransactionHelper::execute(function () use ($device, $data, $clientUuid, $session, $rating) {
            $feedback = MoodCounterFeedback::create([
                'client_uuid' => $clientUuid,
                'session_id' => $session->id,
                'tenant_id' => $device->tenant_id,
                'ticket_id' => $session->ticket_id,
                'counter_id' => $session->counter_id,
                'officer_id' => $session->officer_id,
                'device_id' => $device->id,
                'rating_option_id' => $rating['option_id'],
                'rating_score' => $rating['score'],
                'reason_id' => $data['reason_id'] ?? null,
                'comment' => $data['comment'] ?? null,
                'submitted_at' => isset($data['created_at']) ? $data['created_at'] : now(),
                'synced_from_offline' => (bool) ($data['synced_from_offline'] ?? false),
            ]);

            $this->sessionService->completeSession($session);

            return $feedback;
        });
    }

    /**
     * @return array{option_id: int|null, score: int}
     */
    private function resolveRating(Device $device, array $data): array
    {
        if (!empty($data['rating_option_id'])) {
            $option = MoodRatingOption::withoutGlobalScope('tenant')
                ->where('id', $data['rating_option_id'])
                ->first();

            if ($option) {
                return ['option_id' => $option->id, 'score' => (int) $option->score];
            }
        }

        $score = (int) ($data['rating'] ?? $data['rating_score'] ?? 0);
        if ($score < 1 || $score > 5) {
            throw new \InvalidArgumentException('Rating must be between 1 and 5');
        }

        return ['option_id' => null, 'score' => $score];
    }
}
