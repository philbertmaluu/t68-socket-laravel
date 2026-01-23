<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_role_id')->constrained('user_roles')->onDelete('cascade');
            $table->string('from_user_id', 50);
            $table->string('to_user_id', 50);
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->timestamp('handover_date');
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->integer('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('from_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('to_user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('from_user_id', 'idx_handovers_from_user_id');
            $table->index('to_user_id', 'idx_handovers_to_user_id');
            $table->index('role_id', 'idx_handovers_role_id');
            $table->index('status', 'idx_handovers_status');
            $table->index('handover_date', 'idx_handovers_handover_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handovers');
    }
};
