<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mood_configurations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('locale', 10)->default('en');
            $table->json('theme')->nullable();
            $table->json('company')->nullable();
            $table->json('messages')->nullable();
            $table->json('timeouts')->nullable();
            $table->json('features')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('active')->default(true);
            $table->string('created_by', 50)->nullable();
            $table->string('updated_by', 50)->nullable();
            $table->string('deleted_by', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'locale'], 'idx_mood_config_tenant_locale');
            $table->index('active', 'idx_mood_config_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mood_configurations');
    }
};
