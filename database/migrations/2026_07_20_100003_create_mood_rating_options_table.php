<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mood_rating_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('key', 50);
            $table->string('title', 100);
            $table->string('emoji', 16);
            $table->string('color', 20);
            $table->unsignedTinyInteger('score');
            $table->string('locale', 10)->default('en');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'key', 'locale'], 'idx_mood_rating_tenant_key_locale');
            $table->index(['tenant_id', 'locale', 'active'], 'idx_mood_rating_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mood_rating_options');
    }
};
