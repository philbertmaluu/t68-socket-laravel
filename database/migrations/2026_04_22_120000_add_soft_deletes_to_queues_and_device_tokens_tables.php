<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('queues', function (Blueprint $table) {
            if (!Schema::hasColumn('queues', 'deleted_at')) {
                $table->softDeletes();
                $table->index('deleted_at', 'idx_queues_deleted_at');
            }
            if (!Schema::hasColumn('queues', 'deleted_by')) {
                $table->string('deleted_by', 50)->nullable();
            }
        });

        Schema::table('device_tokens', function (Blueprint $table) {
            if (!Schema::hasColumn('device_tokens', 'deleted_at')) {
                $table->softDeletes();
                $table->index('deleted_at', 'idx_device_tokens_deleted_at');
            }
            if (!Schema::hasColumn('device_tokens', 'deleted_by')) {
                $table->string('deleted_by', 50)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('queues', function (Blueprint $table) {
            if (Schema::hasColumn('queues', 'deleted_at')) {
                $table->dropIndex('idx_queues_deleted_at');
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('queues', 'deleted_by')) {
                $table->dropColumn('deleted_by');
            }
        });

        Schema::table('device_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('device_tokens', 'deleted_at')) {
                $table->dropIndex('idx_device_tokens_deleted_at');
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('device_tokens', 'deleted_by')) {
                $table->dropColumn('deleted_by');
            }
        });
    }
};

