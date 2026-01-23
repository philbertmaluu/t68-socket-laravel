<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->string('role_code', 100);
            $table->string('role_name', 100);
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('module_id', 'idx_roles_module_id');
            $table->index('role_code', 'idx_roles_role_code');
            $table->index('deleted_at', 'idx_roles_deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
