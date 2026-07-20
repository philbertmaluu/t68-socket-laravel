<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mood_offline_sync_queue', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->unsignedBigInteger('device_id');
            $table->string('feedback_type', 20);
            $table->json('payload');
            $table->string('status', 20)->default('processed');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->index(['device_id', 'feedback_type'], 'idx_mood_offline_device_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mood_offline_sync_queue');
    }
};
