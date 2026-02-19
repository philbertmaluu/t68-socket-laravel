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
        // Only add queue_position if tickets table exists and column doesn't exist
        if (Schema::hasTable('tickets') && !Schema::hasColumn('tickets', 'queue_position')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->integer('queue_position')->nullable()->after('status');
                $table->index(['queue_id', 'status', 'queue_position'], 'idx_tickets_queue_status_position');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tickets') && Schema::hasColumn('tickets', 'queue_position')) {
            Schema::table('tickets', function (Blueprint $table) {
                if (Schema::hasIndex('tickets', 'idx_tickets_queue_status_position')) {
                    $table->dropIndex('idx_tickets_queue_status_position');
                }
                $table->dropColumn('queue_position');
            });
        }
    }
};
