<?php

namespace App\Domains\Mood\Services;

use App\Domains\Device\Models\Device;
use App\Domains\Mood\Models\MoodGeneralFeedback;
use App\Domains\Mood\Models\MoodRatingOption;
use App\Shared\Helpers\TransactionHelper;
use Illuminate\Support\Str;

class MoodGeneralFeedbackService
{
    public function submit(Device $device, array $data): MoodGeneralFeedback
    {
        if (!$device->isMoodGeneralMode()) {
            throw new \RuntimeException('Device is not in general feedback mode');
        }

        $clientUuid = (string) ($data['client_uuid'] ?? Str::uuid()->toString());
        $rating = $this->resolveRating($device, $data);

        $existing = MoodGeneralFeedback::where('client_uuid', $clientUuid)->first();
        if ($existing) {
            return $existing;
        }

        return TransactionHelper::execute(function () use ($device, $data, $clientUuid, $rating) {
            return MoodGeneralFeedback::create([
                'client_uuid' => $clientUuid,
                'tenant_id' => $device->tenant_id,
                'branch_id' => (string) $device->office_id,
                'device_id' => $device->id,
                'rating_option_id' => $rating['option_id'],
                'rating_score' => $rating['score'],
                'reason_id' => $data['reason_id'] ?? null,
                'comment' => $data['comment'] ?? null,
                'submitted_at' => isset($data['created_at']) ? $data['created_at'] : now(),
                'synced_from_offline' => (bool) ($data['synced_from_offline'] ?? false),
            ]);
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
