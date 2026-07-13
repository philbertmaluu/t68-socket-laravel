<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Office-scoped assignment of catalog services.
     * services = global catalog; office_services = which office offers which service.
     */
    public function up(): void
    {
        Schema::create('office_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('office_id', 50);
            $table->string('office_name', 200)->nullable();
            $table->string('region_id', 50)->nullable();
            $table->string('region_name', 200)->nullable();
            $table->unsignedBigInteger('service_id');
            $table->string('service_name', 200);
            $table->string('created_by', 50)->nullable();
            $table->string('updated_by', 50)->nullable();
            $table->string('deleted_by', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
            $table->unique(['office_id', 'service_id'], 'office_services_office_service_unique');
            $table->index('tenant_id', 'idx_office_services_tenant_id');
            $table->index('office_id', 'idx_office_services_office_id');
            $table->index('service_id', 'idx_office_services_service_id');
            $table->index('region_id', 'idx_office_services_region_id');
            $table->index('deleted_at', 'idx_office_services_deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_services');
    }
};
