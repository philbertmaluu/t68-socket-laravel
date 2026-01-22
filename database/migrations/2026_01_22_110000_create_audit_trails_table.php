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
        Schema::create('audit_trails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable();
            $table->string('auditable_type', 255);
            $table->unsignedBigInteger('auditable_id');
            $table->string('event', 50); // created, updated, deleted, restored
            $table->string('user_id', 50)->nullable();
            $table->string('user_type', 255)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('url')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->text('tags')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Indexes
            $table->index('tenant_id', 'idx_audit_trails_tenant_id');
            $table->index(['auditable_type', 'auditable_id'], 'idx_audit_trails_auditable');
            $table->index('event', 'idx_audit_trails_event');
            $table->index('user_id', 'idx_audit_trails_user_id');
            $table->index('created_at', 'idx_audit_trails_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_trails');
    }
};
