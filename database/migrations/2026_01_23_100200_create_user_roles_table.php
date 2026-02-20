<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamp('start_date');
            $table->timestamp('end_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedBigInteger('handover_to_user_id')->nullable();
            $table->timestamp('handover_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('handover_to_user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->index('user_id', 'idx_user_roles_user_id');
            $table->index('role_id', 'idx_user_roles_role_id');
            $table->index('status', 'idx_user_roles_status');
            $table->index('start_date', 'idx_user_roles_start_date');
            $table->index('end_date', 'idx_user_roles_end_date');
            $table->index('deleted_at', 'idx_user_roles_deleted_at');
        });

        if (Schema::getConnection()->getDriverName() === 'oracle') {
            DB::statement("ALTER TABLE user_roles ADD CONSTRAINT chk_user_roles_status CHECK (status IN ('active', 'handover', 'inactive'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
