<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            if (!Schema::hasColumn('devices', 'counter_id')) {
                $table->string('counter_id', 50)->nullable()->after('office_id');
                $table->index('counter_id', 'idx_devices_counter_id');
            }
            if (!Schema::hasColumn('devices', 'mood_mode')) {
                $table->string('mood_mode', 20)->nullable()->after('type');
                $table->index('mood_mode', 'idx_devices_mood_mode');
            }
            if (!Schema::hasColumn('devices', 'device_uuid')) {
                $table->string('device_uuid', 64)->nullable()->unique('idx_devices_device_uuid');
            }
            if (!Schema::hasColumn('devices', 'mood_config')) {
                $table->json('mood_config')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            if (Schema::hasColumn('devices', 'counter_id')) {
                $table->dropIndex('idx_devices_counter_id');
                $table->dropColumn('counter_id');
            }
            if (Schema::hasColumn('devices', 'mood_mode')) {
                $table->dropIndex('idx_devices_mood_mode');
                $table->dropColumn('mood_mode');
            }
            if (Schema::hasColumn('devices', 'device_uuid')) {
                $table->dropUnique('idx_devices_device_uuid');
                $table->dropColumn('device_uuid');
            }
            if (Schema::hasColumn('devices', 'mood_config')) {
                $table->dropColumn('mood_config');
            }
        });
    }
};
