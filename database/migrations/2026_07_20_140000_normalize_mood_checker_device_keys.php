<?php

use App\Domains\Device\Models\Device;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mood checkers created before 5-char keys used kiosk-length (10) keys.
     * Normalize all mood checker keys to exactly 5 characters.
     */
    public function up(): void
    {
        $devices = DB::table('devices')
            ->where('type', Device::TYPE_MOOD_CHECKER)
            ->whereNull('deleted_at')
            ->get(['id', 'device_key']);

        foreach ($devices as $device) {
            $key = strtoupper(trim((string) ($device->device_key ?? '')));
            if (strlen($key) === Device::DEVICE_KEY_LENGTH_MOOD) {
                continue;
            }

            do {
                $newKey = Device::generateDeviceKey(Device::TYPE_MOOD_CHECKER);
            } while (
                DB::table('devices')
                    ->where('device_key', $newKey)
                    ->where('id', '!=', $device->id)
                    ->exists()
            );

            DB::table('devices')->where('id', $device->id)->update([
                'device_key' => $newKey,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keys cannot be restored to their previous values.
    }
};
