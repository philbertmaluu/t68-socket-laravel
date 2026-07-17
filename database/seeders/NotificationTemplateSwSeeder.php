<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationTemplateSwSeeder extends Seeder
{
    /**
     * Seed default Swahili notification templates for tenant_id = 1.
     */
    public function run(): void
    {
        $now = now();
        $tenantId = 1;

        $templates = [
            [
                'key' => 'ticket_created_sms',
                'body' => "Ndugu {memberName},\nTiketi yako namba {ticketNumber} imeundwa kwa ajili ya huduma ya {serviceType}.\nTafadhali subiri kuitwa ili kupokea huduma.\nAsante",
                'description' => 'SMS sent when a ticket is created.',
            ],
            [
                'key' => 'ticket_completed_sms',
                'body' => "Ndugu {memberName},\nHuduma yako kwa tiketi namba {ticketNumber} ({serviceType}) imekamilika.\nTafadhali toa maoni yako: {feedbackUrl}\nAsante kwa kutumia huduma za NSSF.\nKaribu tena!",
                'description' => 'SMS sent when a ticket is completed.',
            ],
            [
                'key' => 'thank_you_visit_sms',
                'body' => "Asante kwa kutembelea ofisi zetu za NSSF.\nTunathamini muda wako na ushirikiano wako.",
                'description' => 'Generic thank you for visit SMS.',
            ],
            [
                'key' => 'feedback_thanks_sms',
                'body' => "Asante kwa maoni yako kuhusu huduma za NSSF.\nMaoni yako yanatusaidia kuboresha huduma zetu.",
                'description' => 'Thank you message after customer feedback.',
            ],
        ];

        foreach ($templates as $template) {
            $existing = DB::table('notification_templates')
                ->where('tenant_id', $tenantId)
                ->where('channel', 'sms')
                ->where('key', $template['key'])
                ->where('locale', 'sw')
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
                'locale' => 'sw',
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
