<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_tool_calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('office_id', 50)->nullable();
            $table->string('role_mode', 30);
            $table->string('tool_name', 100);
            $table->longText('arguments')->nullable();
            $table->longText('result_payload')->nullable();
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('tenant_id', 'idx_bot_tool_calls_tenant_id');
            $table->index('user_id', 'idx_bot_tool_calls_user_id');
            $table->index('office_id', 'idx_bot_tool_calls_office_id');
            $table->index('role_mode', 'idx_bot_tool_calls_role_mode');
            $table->index('tool_name', 'idx_bot_tool_calls_tool_name');
            $table->index('created_at', 'idx_bot_tool_calls_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_tool_calls');
    }
};
