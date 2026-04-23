<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('feedback_type', 20); // general|ticket
            $table->unsignedTinyInteger('rating');
            $table->string('comment_key', 100)->nullable();
            $table->string('comment_label', 255)->nullable();
            $table->text('comment_text')->nullable();
            $table->string('general_comment', 50)->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('ticket_number', 50)->nullable();
            $table->string('clerk_id', 50)->nullable();
            $table->unsignedTinyInteger('clerk_rating')->nullable();
            $table->string('office_id', 50)->nullable();
            $table->string('source', 100)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('created_by', 50)->nullable();
            $table->string('updated_by', 50)->nullable();
            $table->string('deleted_by', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('ticket_id')->references('id')->on('tickets')->nullOnDelete();

            $table->index(['tenant_id', 'feedback_type'], 'idx_feedbacks_tenant_type');
            $table->index('ticket_id', 'idx_feedbacks_ticket_id');
            $table->index('clerk_id', 'idx_feedbacks_clerk_id');
            $table->index('submitted_at', 'idx_feedbacks_submitted_at');
            $table->index('office_id', 'idx_feedbacks_office_id');
            $table->index('deleted_at', 'idx_feedbacks_deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
