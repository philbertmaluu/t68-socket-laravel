<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mood_general_feedback', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_uuid')->unique();
            $table->unsignedBigInteger('tenant_id');
            $table->string('branch_id', 50)->nullable();
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
            $table->index(['tenant_id', 'submitted_at'], 'idx_mood_general_tenant_submitted');
            $table->index('device_id', 'idx_mood_general_device');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mood_general_feedback');
    }
};
