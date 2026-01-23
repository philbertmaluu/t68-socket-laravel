<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('module_id', 100);
            $table->string('code', 100);    
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('created_by')->nullable();
            $table->integer('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name', 'idx_modules_module_name');
            $table->index('is_active', 'idx_modules_is_active');
            $table->index('deleted_at', 'idx_modules_deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
