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
        Schema::create('counter_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->string('name', 200);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('ACTIVE')->comment("Values: 'ACTIVE', 'INACTIVE'");
            $table->string('created_by', 50)->nullable();
            $table->string('updated_by', 50)->nullable();
            $table->string('deleted_by', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Indexes
            $table->index('tenant_id', 'idx_counter_types_tenant_id');
            $table->index('status', 'idx_counter_types_status');
            $table->index('deleted_at', 'idx_counter_types_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('counter_types');
    }
};
