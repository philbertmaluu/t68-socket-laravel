<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 50);
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->timestamp('start_date');
            $table->timestamp('end_date')->nullable();
            $table->enum('status', ['active', 'handover', 'inactive'])->default('active');
            $table->string('handover_to_user_id', 50)->nullable();
            $table->timestamp('handover_date')->nullable();
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('handover_to_user_id')->references('id')->on('users')->onDelete('set null');

            $table->index('user_id', 'idx_user_roles_user_id');
            $table->index('role_id', 'idx_user_roles_role_id');
            $table->index('status', 'idx_user_roles_status');
            $table->index('start_date', 'idx_user_roles_start_date');
            $table->index('end_date', 'idx_user_roles_end_date');
            $table->index('deleted_at', 'idx_user_roles_deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
