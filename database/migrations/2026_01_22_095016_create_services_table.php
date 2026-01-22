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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 50);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->integer('estimated_time')->comment('in minutes');
            $table->string('status', 20)->default('ACTIVE')->comment("Values: 'ACTIVE', 'INACTIVE'");
            $table->string('region_id', 50);
            $table->string('office_id', 50);
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Indexes
            $table->index('id', 'idx_services_id');
            $table->index('tenant_id', 'idx_services_tenant_id');
            $table->index('office_id', 'idx_services_office_id');
            $table->index('region_id', 'idx_services_region_id');
            $table->index('status', 'idx_services_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
