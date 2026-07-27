<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_announce_locks', function (Blueprint $table) {
            $table->string('office_id', 50)->primary();
            $table->string('current_announce_id', 36)->nullable();
            $table->boolean('is_announcing')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_announce_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('office_id', 50);
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('ticket_number', 50);
            $table->string('counter_name', 100)->nullable();
            $table->string('counter_type_name', 100)->nullable();
            $table->string('counter_type_code', 50)->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['office_id', 'status'], 'idx_announce_jobs_office_status');
            $table->index('ticket_id', 'idx_announce_jobs_ticket');
        });

        Schema::create('pending_ticket_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('office_id', 50);
            $table->string('clerk_id', 50);
            $table->string('status', 20)->default('waiting');
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->timestamp('requested_at');
            $table->timestamps();

            $table->index(['office_id', 'status', 'requested_at'], 'idx_pending_calls_office');
            $table->index(['clerk_id', 'status'], 'idx_pending_calls_clerk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_ticket_calls');
        Schema::dropIfExists('ticket_announce_jobs');
        Schema::dropIfExists('office_announce_locks');
    }
};
