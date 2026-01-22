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
        Schema::create('service_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id');
            $table->string('service_id', 50);
            $table->string('document_name', 200);
            $table->boolean('is_required')->default(true);
            $table->integer('order_index')->default(0);
            $table->string('created_by', 50)->nullable();
            $table->string('updated_by', 50)->nullable();
            $table->string('deleted_by', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            // Indexes
            $table->index('tenant_id', 'idx_service_documents_tenant_id');
            $table->index('service_id', 'idx_service_documents_service_id');
            $table->index('deleted_at', 'idx_service_documents_deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_documents');
    }
};
