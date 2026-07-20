<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mood_feedback_reasons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('key', 50);
            $table->string('title', 150);
            $table->string('category', 50)->default('general');
            $table->json('applies_to_ratings')->nullable();
            $table->string('locale', 10)->default('en');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'key', 'locale'], 'idx_mood_reason_tenant_key_locale');
            $table->index(['tenant_id', 'locale', 'category'], 'idx_mood_reason_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mood_feedback_reasons');
    }
};
