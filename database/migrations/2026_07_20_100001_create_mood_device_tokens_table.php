<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mood_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->string('access_token', 128)->unique();
            $table->string('refresh_token', 128)->unique();
            $table->string('device_uuid', 64)->nullable();
            $table->timestamp('access_expires_at');
            $table->timestamp('refresh_expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->string('deleted_by', 50)->nullable();

            $table->foreign('device_id')->references('id')->on('devices')->onDelete('cascade');
            $table->index(['device_id', 'access_expires_at'], 'idx_mood_tokens_device_access');
            $table->index('refresh_token', 'idx_mood_tokens_refresh');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mood_device_tokens');
    }
};
