<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mood_counter_feedback', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->uuid('session_id')->nullable();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('counter_id', 50)->nullable();
            $table->string('officer_id', 50)->nullable();
            $table->unsignedBigInteger('device_id');
            $table->unsignedBigInteger('rating_option_id')->nullable();
            $table->unsignedTinyInteger('rating_score');
            $table->unsignedBigInteger('reason_id')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('submitted_at');
            $table->boolean('synced_from_offline')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->foreign('session_id')->references('id')->on('mood_feedback_sessions')->nullOnDelete();
            $table->index(['tenant_id', 'submitted_at'], 'idx_mood_counter_tenant_submitted');
            $table->index('session_id', 'idx_mood_counter_session');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mood_counter_feedback');
    }
};
