<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('self_service_members', 'email')) {
            Schema::table('self_service_members', function (Blueprint $table) {
                $table->string('email', 255)->nullable();
            });
        }

        if (!Schema::hasColumn('self_service_members', 'synced_at')) {
            Schema::table('self_service_members', function (Blueprint $table) {
                $table->timestamp('synced_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('self_service_members', function (Blueprint $table) {
            if (Schema::hasColumn('self_service_members', 'synced_at')) {
                $table->dropColumn('synced_at');
            }
            if (Schema::hasColumn('self_service_members', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};
