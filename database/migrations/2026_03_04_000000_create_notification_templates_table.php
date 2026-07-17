<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('key', 191);
            $table->string('channel', 50)->default('sms'); // sms, email, push, etc.
            $table->string('locale', 10)->default('sw');   // e.g. sw, en
            $table->string('subject', 255)->nullable();
            $table->text('body');
            $table->string('description', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->string('created_by', 50)->nullable();
            $table->string('updated_by', 50)->nullable();
            $table->string('deleted_by', 50)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'key', 'channel', 'locale'], 'idx_notification_templates_unique');
            $table->index('tenant_id', 'idx_notification_templates_tenant_id');
            $table->index('channel', 'idx_notification_templates_channel');
            $table->index('locale', 'idx_notification_templates_locale');
            $table->index('active', 'idx_notification_templates_active');
        });

        // Ensure default tenant exists before seeding templates.
        if (!DB::table('tenants')->where('id', 1)->exists()) {
            DB::table('tenants')->insert([
                'id' => 1,
                'name' => 'NSSF',
                'domain' => 'https://portal.nssf.go.tz/',
                'database' => 'QMS-DB',
                'is_active' => true,
                'settings' => json_encode([
                    'timezone' => 'UTC',
                    'locale' => 'en',
                ]),
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Seed a few default templates so the system works immediately.
        // Content managers can edit these via the frontend.
        DB::table('notification_templates')->insert([
            [
                'tenant_id' => 1,
                'key' => 'ticket_created_sms',
                'channel' => 'sms',
                'locale' => 'sw',
                'subject' => null,
                'body' => "Ndugu {memberName},\nTiketi yako namba {ticketNumber} imeundwa kwa ajili ya huduma ya {serviceType}.\nTafadhali subiri kuitwa ili kupokea huduma.\nAsante",
                'description' => 'SMS sent when a ticket is created.',
                'active' => true,
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => 1,
                'key' => 'ticket_completed_sms',
                'channel' => 'sms',
                'locale' => 'sw',
                'subject' => null,
                'body' => "Ndugu {memberName},\nHuduma yako kwa tiketi namba {ticketNumber} ({serviceType}) imekamilika.\nTafadhali toa maoni yako: {feedbackUrl}\nAsante kwa kutumia huduma za NSSF.\nKaribu tena!",
                'description' => 'SMS sent when a ticket is completed.',
                'active' => true,
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => 1,
                'key' => 'thank_you_visit_sms',
                'channel' => 'sms',
                'locale' => 'sw',
                'subject' => null,
                'body' => "Asante kwa kutembelea ofisi zetu za NSSF.\nTunathamini muda wako na ushirikiano wako.",
                'description' => 'Generic thank you for visit SMS.',
                'active' => true,
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => 1,
                'key' => 'feedback_thanks_sms',
                'channel' => 'sms',
                'locale' => 'sw',
                'subject' => null,
                'body' => "Asante kwa maoni yako kuhusu huduma za NSSF.\nMaoni yako yanatusaidia kuboresha huduma zetu.",
                'description' => 'Thank you message after customer feedback.',
                'active' => true,
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};

