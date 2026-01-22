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
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->string('name', 200);
            $table->enum('type', ['kiosk', 'tv'])->default('kiosk');
            $table->enum('status', ['online', 'offline', 'maintenance'])->default('offline');
            $table->string('region_id', 50);
            $table->string('office_id', 50);
            $table->string('serial_number', 100)->unique();
            $table->string('ip_address', 50)->nullable();
            $table->string('password', 255)->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->text('notes')->nullable();
            $table->string('created_by', 50)->nullable();
            $table->string('updated_by', 50)->nullable();
            $table->string('deleted_by', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            // TODO: Uncomment when regions and offices tables are created
            // $table->foreign('region_id')->references('id')->on('regions')->onDelete('restrict');
            // $table->foreign('office_id')->references('id')->on('offices')->onDelete('restrict');

            // Indexes
            $table->index('tenant_id', 'idx_devices_tenant_id');
            $table->index('office_id', 'idx_devices_office_id');
            $table->index('region_id', 'idx_devices_region_id');
            $table->index('type', 'idx_devices_type');
            $table->index('status', 'idx_devices_status');
            $table->index('serial_number', 'idx_devices_serial_number');
            $table->index('deleted_at', 'idx_devices_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
