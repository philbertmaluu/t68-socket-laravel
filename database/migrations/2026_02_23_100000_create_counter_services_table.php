<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Pivot table: one counter can have multiple services (counter_id, service_id, office_id).
     */
    public function up(): void
    {
        Schema::create('counter_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('counter_id');
            $table->unsignedBigInteger('service_id');
            $table->string('office_id', 50);
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('counter_id')->references('id')->on('counters')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');

            $table->unique(['counter_id', 'service_id'], 'counter_services_counter_service_unique');
            $table->index('counter_id', 'idx_counter_services_counter_id');
            $table->index('service_id', 'idx_counter_services_service_id');
            $table->index('office_id', 'idx_counter_services_office_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('counter_services');
    }
};
