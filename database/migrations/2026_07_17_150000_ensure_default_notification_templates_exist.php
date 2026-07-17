<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ensure default global SMS templates exist (tenant_id null).
     * Safe to run on environments where the original seed insert was skipped or wiped.
     */
    public function up(): void
    {
        $now = now();

        $templates = [
            [
                'key' => 'ticket_created_sms',
                'locale' => 'sw',
                'body' => "Ndugu {memberName},\nTiketi yako namba {ticketNumber} imeundwa kwa ajili ya huduma ya {serviceType}.\nTafadhali subiri kuitwa ili kupokea huduma.\nAsante",
                'description' => 'SMS sent when a ticket is created.',
            ],
            [
                'key' => 'ticket_completed_sms',
                'locale' => 'sw',
                'body' => "Ndugu {memberName},\nHuduma yako kwa tiketi namba {ticketNumber} ({serviceType}) imekamilika.\nTafadhali toa maoni yako: {feedbackUrl}\nAsante kwa kutumia huduma za NSSF.\nKaribu tena!",
                'description' => 'SMS sent when a ticket is completed.',
            ],
            [
                'key' => 'thank_you_visit_sms',
                'locale' => 'sw',
                'body' => "Asante kwa kutembelea ofisi zetu za NSSF.\nTunathamini muda wako na ushirikiano wako.",
                'description' => 'Generic thank you for visit SMS.',
            ],
            [
                'key' => 'feedback_thanks_sms',
                'locale' => 'sw',
                'body' => "Asante kwa maoni yako kuhusu huduma za NSSF.\nMaoni yako yanatusaidia kuboresha huduma zetu.",
                'description' => 'Thank you message after customer feedback.',
            ],
            [
                'key' => 'ticket_created_sms',
                'locale' => 'en',
                'body' => "Dear {memberName},\nYour ticket number {ticketNumber} has been created for {serviceType}.\nPlease wait to be called for service.\nThank you.",
                'description' => 'SMS sent when a ticket is created (English).',
            ],
            [
                'key' => 'ticket_completed_sms',
                'locale' => 'en',
                'body' => "Dear {memberName},\nYour service for ticket number {ticketNumber} ({serviceType}) has been completed.\nPlease share your feedback: {feedbackUrl}\nThank you for using NSSF services.\nWelcome again!",
                'description' => 'SMS sent when a ticket is completed (English).',
            ],
            [
                'key' => 'thank_you_visit_sms',
                'locale' => 'en',
                'body' => "Thank you for visiting our NSSF offices.\nWe appreciate your time and cooperation.",
                'description' => 'Generic thank you for visit SMS (English).',
            ],
            [
                'key' => 'feedback_thanks_sms',
                'locale' => 'en',
                'body' => "Thank you for sharing your feedback about NSSF services.\nYour comments help us improve our service delivery.",
                'description' => 'Thank you message after customer feedback (English).',
            ],
        ];

        foreach ($templates as $template) {
            $exists = DB::table('notification_templates')
                ->whereNull('tenant_id')
                ->where('key', $template['key'])
                ->where('channel', 'sms')
                ->where('locale', $template['locale'])
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('notification_templates')->insert([
                'tenant_id' => null,
                'key' => $template['key'],
                'channel' => 'sms',
                'locale' => $template['locale'],
                'subject' => null,
                'body' => $template['body'],
                'description' => $template['description'],
                'active' => true,
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Non-destructive seed migration; nothing to roll back.
    }
};
