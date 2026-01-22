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
        Schema::create('counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->string('name', 200);
            $table->foreignId('type');
            $table->foreignId('service_id');
            $table->string('status', 20)->default('ACTIVE')->comment("Values: 'ACTIVE', 'INACTIVE', 'MAINTENANCE'");
            $table->string('office_id', 50);
            $table->string('created_by', 50)->nullable();
            $table->string('updated_by', 50)->nullable();
            $table->string('deleted_by', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('type')->references('id')->on('counter_types')->onDelete('restrict');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('restrict');

            // Indexes
            $table->index('tenant_id', 'idx_counters_tenant_id');
            $table->index('office_id', 'idx_counters_office_id');
            $table->index('status', 'idx_counters_status');
            $table->index('type', 'idx_counters_type');
            $table->index('service_id', 'idx_counters_service_id');
            $table->index('deleted_at', 'idx_counters_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('counters');
    }
};
