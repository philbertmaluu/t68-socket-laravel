<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationTemplateEnSeeder extends Seeder
{
    /**
     * Seed default English notification templates for tenant_id = 1.
     */
    public function run(): void
    {
        $now = now();
        $tenantId = 1;

        $templates = [
            [
                'key' => 'ticket_created_sms',
                'body' => "Dear {memberName},\nYour ticket number {ticketNumber} has been created for {serviceType}.\nPlease wait to be called for service.\nThank you.",
                'description' => 'SMS sent when a ticket is created (English).',
            ],
            [
                'key' => 'ticket_completed_sms',
                'body' => "Dear {memberName},\nYour service for ticket number {ticketNumber} ({serviceType}) has been completed.\nPlease share your feedback: {feedbackUrl}\nThank you for using NSSF services.\nWelcome again!",
                'description' => 'SMS sent when a ticket is completed (English).',
            ],
            [
                'key' => 'ticket_called_sms',
                'body' => 'Dear Customer, your ticket has been called. Please proceed to {counterTypeName} number {counterName} to receive service.',
                'description' => 'SMS sent when a ticket is called or recalled (English).',
            ],
            [
                'key' => 'ticket_transfer_accepted_sms',
                'body' => 'Dear Customer, your ticket has been transferred. Please proceed to {counterTypeName} number {counterName} to receive service.',
                'description' => 'SMS sent when a transferred ticket is accepted (English).',
            ],
            [
                'key' => 'thank_you_visit_sms',
                'body' => "Thank you for visiting our NSSF offices.\nWe appreciate your time and cooperation.",
                'description' => 'Generic thank you for visit SMS (English).',
            ],
            [
                'key' => 'feedback_thanks_sms',
                'body' => "Thank you for sharing your feedback about NSSF services.\nYour comments help us improve our service delivery.",
                'description' => 'Thank you message after customer feedback (English).',
            ],
        ];

        foreach ($templates as $template) {
            $existing = DB::table('notification_templates')
                ->where('tenant_id', $tenantId)
                ->where('channel', 'sms')
                ->where('key', $template['key'])
                ->where('locale', 'en')
                ->whereNull('deleted_at')
                ->first();

            if ($existing) {
                DB::table('notification_templates')
                    ->where('id', $existing->id)
                    ->update([
                        'active' => true,
                        'body' => $template['body'],
                        'description' => $template['description'],
                        'updated_at' => $now,
                    ]);
                continue;
            }

            DB::table('notification_templates')->insert([
                'tenant_id' => $tenantId,
                'key' => $template['key'],
                'channel' => 'sms',
                'locale' => 'en',
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
}
