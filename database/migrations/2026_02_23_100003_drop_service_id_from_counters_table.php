<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Remove legacy service_id from counters; use counter_services pivot only.
     */
    public function up(): void
    {
        Schema::table('counters', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropIndex('idx_counters_service_id');
            $table->dropColumn('service_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('counters', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable()->after('counter_type_id');
            $table->foreign('service_id')->references('id')->on('services');
            $table->index('service_id', 'idx_counters_service_id');
        });
    }
};
