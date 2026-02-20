<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('queues', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key
            $table->unsignedBigInteger('counter_id');
            $table->string('name', 200); // Queue name (usually same as counter name)
            $table->string('status', 20)->default('NORMAL');
            $table->integer('members_waiting')->default(0); // Number of tickets waiting
            $table->integer('members_being_served')->default(0); // Number of tickets currently being served
            $table->integer('average_wait_time')->default(0); // Average wait time in minutes
            $table->string('office_id', 50); // Office where this counter/queue is located
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('counter_id')->references('id')->on('counters')->onDelete('cascade');

            // Indexes
            $table->index('counter_id', 'idx_queues_counter_id');
            $table->index('office_id', 'idx_queues_office_id');
            $table->index('status', 'idx_queues_status');
            
            // Unique constraint: one queue per counter
            $table->unique('counter_id', 'idx_queues_counter_unique');
        });

        if (Schema::getConnection()->getDriverName() === 'oracle') {
            DB::statement("ALTER TABLE queues ADD CONSTRAINT chk_queues_status CHECK (status IN ('BUSY', 'NORMAL', 'CRITICAL', 'FREE'))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queues');
    }
};
