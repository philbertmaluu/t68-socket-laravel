<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationTemplateEnSeeder extends Seeder
{
    /**
     * Seed default English notification templates.
     */
    public function run(): void
    {
        $now = now();

        // Avoid duplicating if already seeded
        $existing = DB::table('notification_templates')
            ->where('channel', 'sms')
            ->whereIn('key', [
                'ticket_created_sms',
                'ticket_completed_sms',
                'thank_you_visit_sms',
                'feedback_thanks_sms',
            ])
            ->where('locale', 'en')
            ->count();

        if ($existing > 0) {
            return;
        }

        DB::table('notification_templates')->insert([
            [
                'tenant_id' => null,
                'key' => 'ticket_created_sms',
                'channel' => 'sms',
                'locale' => 'en',
                'subject' => null,
                'body' => "Dear {memberName},\nYour ticket number {ticketNumber} has been created for {serviceType}.\nPlease wait to be called for service.\nThank you.",
                'description' => 'SMS sent when a ticket is created (English).',
                'active' => true,
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => null,
                'key' => 'ticket_completed_sms',
                'channel' => 'sms',
                'locale' => 'en',
                'subject' => null,
                'body' => "Dear {memberName},\nYour service for ticket number {ticketNumber} ({serviceType}) has been completed.\nPlease share your feedback: {feedbackUrl}\nThank you for using NSSF services.\nWelcome again!",
                'description' => 'SMS sent when a ticket is completed (English).',
                'active' => true,
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => null,
                'key' => 'thank_you_visit_sms',
                'channel' => 'sms',
                'locale' => 'en',
                'subject' => null,
                'body' => "Thank you for visiting our NSSF offices.\nWe appreciate your time and cooperation.",
                'description' => 'Generic thank you for visit SMS (English).',
                'active' => true,
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'tenant_id' => null,
                'key' => 'feedback_thanks_sms',
                'channel' => 'sms',
                'locale' => 'en',
                'subject' => null,
                'body' => "Thank you for sharing your feedback about NSSF services.\nYour comments help us improve our service delivery.",
                'description' => 'Thank you message after customer feedback (English).',
                'active' => true,
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}

