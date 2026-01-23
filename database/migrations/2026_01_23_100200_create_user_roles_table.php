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
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->timestamp('start_date');
            $table->timestamp('end_date')->nullable();
            $table->enum('status', ['active', 'handover', 'inactive'])->default('active');
            $table->foreignId('handover_to_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('handover_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

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
