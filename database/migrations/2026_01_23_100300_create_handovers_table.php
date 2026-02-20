<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handovers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_role_id');
            $table->unsignedBigInteger('from_user_id');
            $table->unsignedBigInteger('to_user_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamp('handover_date');
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_role_id')->references('id')->on('user_roles')->onDelete('cascade');
            $table->foreign('from_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('to_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->index('from_user_id', 'idx_handovers_from_user_id');
            $table->index('to_user_id', 'idx_handovers_to_user_id');
            $table->index('role_id', 'idx_handovers_role_id');
            $table->index('status', 'idx_handovers_status');
            $table->index('handover_date', 'idx_handovers_handover_date');
        });

        if (Schema::getConnection()->getDriverName() === 'oracle') {
            DB::statement("ALTER TABLE handovers ADD CONSTRAINT chk_handovers_status CHECK (status IN ('active', 'completed', 'cancelled'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('handovers');
    }
};
