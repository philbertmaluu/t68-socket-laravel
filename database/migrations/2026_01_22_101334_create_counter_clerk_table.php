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
        Schema::create('counter_clerk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('counter_id')->constrained('counters')->onDelete('cascade');
            $table->string('clerk_id', 50);
            $table->boolean('is_active')->default(true);
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('unassigned_at')->nullable();
            $table->string('created_by', 50)->nullable();
            $table->string('updated_by', 50)->nullable();
            $table->string('deleted_by', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Indexes
            $table->index('tenant_id', 'idx_counter_clerk_tenant_id');
            $table->index('counter_id', 'idx_counter_clerk_counter_id');
            $table->index('clerk_id', 'idx_counter_clerk_clerk_id');
            $table->index('is_active', 'idx_counter_clerk_is_active');
            $table->index(['counter_id', 'clerk_id'], 'idx_counter_clerk_counter_clerk');
            $table->index('deleted_at', 'idx_counter_clerk_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('counter_clerk');
    }
};
