<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('office_id', 50)->nullable();
            $table->string('role_mode', 30);
            $table->text('message');
            $table->longText('response');
            $table->unsignedInteger('tool_calls_count')->default(0);
            $table->longText('meta')->nullable();
            $table->timestamps();

            $table->index('tenant_id', 'idx_bot_conversations_tenant_id');
            $table->index('user_id', 'idx_bot_conversations_user_id');
            $table->index('office_id', 'idx_bot_conversations_office_id');
            $table->index('role_mode', 'idx_bot_conversations_role_mode');
            $table->index('created_at', 'idx_bot_conversations_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_conversations');
    }
};
