<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Oracle ships with CHK_DEVICES_TYPE allowing only kiosk/tv.
     * Mood Checker devices use type MOOD_CHECKER.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'oracle') {
            return;
        }

        DB::statement('ALTER TABLE devices DROP CONSTRAINT chk_devices_type');

        DB::statement("ALTER TABLE devices ADD CONSTRAINT chk_devices_type CHECK (type IN ('KIOSK', 'TV', 'MOOD_CHECKER', 'kiosk', 'tv'))");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'oracle') {
            return;
        }

        DB::statement('ALTER TABLE devices DROP CONSTRAINT chk_devices_type');

        DB::statement("ALTER TABLE devices ADD CONSTRAINT chk_devices_type CHECK (type IN ('kiosk', 'tv'))");
    }
};
