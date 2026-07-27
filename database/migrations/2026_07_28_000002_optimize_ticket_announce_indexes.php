<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * If the first create migration already ran with heavier indexes, slim them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ticket_announce_jobs')) {
            return;
        }

        Schema::table('ticket_announce_jobs', function (Blueprint $table) {
            foreach (['idx_announce_jobs_ticket', 'idx_announce_jobs_office_status'] as $name) {
                try {
                    $table->dropIndex($name);
                } catch (\Throwable) {
                }
            }
        });

        // Ensure covering index exists (create migration may already have it).
        try {
            Schema::table('ticket_announce_jobs', function (Blueprint $table) {
                $table->index(
                    ['office_id', 'status', 'created_at'],
                    'idx_aj_office_status_created'
                );
            });
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        // no-op
    }
};
