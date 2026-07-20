<?php

namespace App\Domains\Mood\Services;

use App\Domains\Device\Models\Device;
use App\Domains\Mood\Models\MoodOfflineSyncRecord;
use App\Shared\Helpers\TransactionHelper;

class MoodOfflineSyncService
{
    private MoodGeneralFeedbackService $generalService;
    private MoodCounterFeedbackService $counterService;

    public function __construct()
    {
        $this->generalService = new MoodGeneralFeedbackService();
        $this->counterService = new MoodCounterFeedbackService();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{processed: int, duplicates: int, failed: array<int, array<string, mixed>>}
     */
    public function syncBatch(Device $device, array $items): array
    {
        $processed = 0;
        $duplicates = 0;
        $failed = [];

        foreach ($items as $index => $item) {
            $clientUuid = (string) ($item['client_uuid'] ?? '');

            if ($clientUuid === '') {
                $failed[] = ['index' => $index, 'error' => 'client_uuid is required'];
                continue;
            }

            $existing = MoodOfflineSyncRecord::where('client_uuid', $clientUuid)->first();
            if ($existing) {
                $duplicates++;
                continue;
            }

            try {
                TransactionHelper::execute(function () use ($device, $item, $clientUuid) {
                    $type = (string) ($item['type'] ?? 'general');
                    $payload = (array) ($item['payload'] ?? $item);
                    $payload['client_uuid'] = $clientUuid;
                    $payload['synced_from_offline'] = true;

                    if ($type === 'counter') {
                        $this->counterService->submit($device, $payload);
                    } else {
                        $this->generalService->submit($device, $payload);
                    }

                    MoodOfflineSyncRecord::create([
                        'client_uuid' => $clientUuid,
                        'device_id' => $device->id,
                        'feedback_type' => $type,
                        'payload' => $payload,
                        'status' => 'processed',
                        'processed_at' => now(),
                    ]);
                });

                $processed++;
            } catch (\Throwable $e) {
                $failed[] = [
                    'index' => $index,
                    'client_uuid' => $clientUuid,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'processed' => $processed,
            'duplicates' => $duplicates,
            'failed' => $failed,
        ];
    }
}
