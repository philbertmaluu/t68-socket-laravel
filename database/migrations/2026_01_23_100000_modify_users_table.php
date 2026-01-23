<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('users');
        
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->onDelete('cascade');
            $table->string('user_id', 50);
            $table->enum('user_type', ['staff', 'member', 'employer', 'supplier'])->default('staff');
            $table->string('name', 100)->unique();
            $table->string('email', 255)->unique()->nullable();
            $table->string('password', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login')->nullable();
            $table->string('refresh_token', 255)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->foreignId('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name', 'idx_users_name');
            $table->index('email', 'idx_users_email');
            $table->index('user_id', 'idx_users_user_id');
            $table->index('tenant_id', 'idx_users_tenant_id');
            $table->index('deleted_at', 'idx_users_deleted_at');
        });
        
        // Add self-referencing foreign keys after table creation
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
