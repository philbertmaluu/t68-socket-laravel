<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_announce_locks', function (Blueprint $table) {
            // One row per office — hot path is PK(office_id) only.
            $table->string('office_id', 50)->primary();
            $table->string('current_announce_id', 36)->nullable();
            $table->boolean('is_announcing')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('ticket_announce_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('office_id', 50);
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('ticket_number', 32);
            $table->string('counter_name', 32)->nullable();
            $table->string('counter_type_name', 32)->nullable();
            $table->string('counter_type_code', 24)->nullable();
            $table->string('status', 10)->default('pending');
            $table->timestamp('created_at')->nullable();

            // Single covering index for "next open job for this office".
            $table->index(
                ['office_id', 'status', 'created_at'],
                'idx_aj_office_status_created'
            );
        });

        Schema::create('pending_ticket_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('office_id', 50);
            $table->string('clerk_id', 50);
            $table->string('status', 12)->default('waiting');
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->timestamp('requested_at');

            $table->index(
                ['office_id', 'status', 'requested_at'],
                'idx_ptc_office_wait'
            );
            $table->index(
                ['clerk_id', 'status'],
                'idx_ptc_clerk_status'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_ticket_calls');
        Schema::dropIfExists('ticket_announce_jobs');
        Schema::dropIfExists('office_announce_locks');
    }
};
