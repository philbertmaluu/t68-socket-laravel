<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mood_feedback_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('counter_id', 50)->nullable();
            $table->string('officer_id', 50)->nullable();
            $table->string('branch_id', 50)->nullable();
            $table->unsignedBigInteger('device_id');
            $table->string('service_id', 50)->nullable();
            $table->string('customer_type', 50)->nullable();
            $table->timestamp('start_time');
            $table->timestamp('expire_time');
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->foreign('ticket_id')->references('id')->on('tickets')->nullOnDelete();
            $table->index(['device_id', 'status'], 'idx_mood_sessions_device_status');
            $table->index('expire_time', 'idx_mood_sessions_expire');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mood_feedback_sessions');
    }
};
