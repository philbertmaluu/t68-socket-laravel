<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('self_service_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('member_number', 50);
            $table->string('member_name', 200)->nullable();
            $table->string('phone', 20);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'member_number'], 'idx_ss_members_unique');
            $table->index('member_number', 'idx_ss_members_number');
            $table->index('tenant_id', 'idx_ss_members_tenant');
        });

        Schema::create('self_service_otp_challenges', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->string('member_number', 50);
            $table->string('member_name', 200)->nullable();
            $table->string('phone', 20);
            $table->string('otp_hash', 255)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('member_number', 'idx_ss_otp_member');
            $table->index('expires_at', 'idx_ss_otp_expires');
            $table->index('device_id', 'idx_ss_otp_device');
        });

        $now = now();
        $templates = [
            [
                'tenant_id' => 1,
                'key' => 'self_service_otp_sms',
                'channel' => 'sms',
                'locale' => 'sw',
                'subject' => null,
                'body' => 'Namba yako ya OTP ni {otp}. Inaisha baada ya dakika {minutes}.',
                'description' => 'SMS OTP for kiosk self-service login.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => 1,
                'key' => 'self_service_otp_sms',
                'channel' => 'sms',
                'locale' => 'en',
                'subject' => null,
                'body' => 'Your OTP is {otp}. It expires in {minutes} minutes. Do not share it with anyone.',
                'description' => 'SMS OTP for kiosk self-service login.',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        if (Schema::hasTable('notification_templates')) {
            foreach ($templates as $template) {
                $exists = DB::table('notification_templates')
                    ->where('key', $template['key'])
                    ->where('channel', $template['channel'])
                    ->where('locale', $template['locale'])
                    ->where('tenant_id', $template['tenant_id'])
                    ->exists();
                if (!$exists) {
                    DB::table('notification_templates')->insert($template);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('self_service_otp_challenges');
        Schema::dropIfExists('self_service_members');
    }
};
