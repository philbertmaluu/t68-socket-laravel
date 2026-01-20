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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 50)->unique();
            $table->string('service_type', 200);
            $table->string('service_id', 50)->nullable();
            $table->string('queue_id', 50);
            $table->string('member_number', 50)->nullable();
            $table->string('member_name', 200)->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->integer('estimated_time')->nullable(); // in seconds
            $table->boolean('priority')->default(false);
            $table->string('status', 20)->default('waiting'); // ticket_status_enum: 'waiting', 'called', 'serving', 'completed', 'skipped', 'transferred', 'cancelled'
            $table->string('counter_id', 50)->nullable();
            $table->string('clerk_id', 50)->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('serving_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->string('transferred_to_counter_id', 50)->nullable();
            $table->string('office_id', 50);
            $table->integer('queue_position')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('ticket_number', 'idx_tickets_ticket_number');
            $table->index('queue_id', 'idx_tickets_queue_id');
            $table->index('counter_id', 'idx_tickets_counter_id');
            $table->index('clerk_id', 'idx_tickets_clerk_id');
            $table->index('status', 'idx_tickets_status');
            $table->index('created_at', 'idx_tickets_created_at');
            $table->index('member_number', 'idx_tickets_member_number');
            $table->index('office_id', 'idx_tickets_office_id');
            $table->index(['queue_id', 'status', 'queue_position'], 'idx_tickets_queue_status_position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
