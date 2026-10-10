<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $templates = [
            [
                'key' => 'ticket_called_sms',
                'locale' => 'sw',
                'body' => 'Ndugu Mteja tiketi yako imeitwa tafadhali elekea {counterTypeName} nambari {counterName} ili kupokea huduma',
                'description' => 'SMS sent when a ticket is called or recalled.',
            ],
            [
                'key' => 'ticket_called_sms',
                'locale' => 'en',
                'body' => 'Dear Customer, your ticket has been called. Please proceed to {counterTypeName} number {counterName} to receive service.',
                'description' => 'SMS sent when a ticket is called or recalled (English).',
            ],
        ];

        foreach ($templates as $template) {
            $exists = DB::table('notification_templates')
                ->where('tenant_id', 1)
                ->where('key', $template['key'])
                ->where('channel', 'sms')
                ->where('locale', $template['locale'])
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                DB::table('notification_templates')
                    ->where('tenant_id', 1)
                    ->where('key', $template['key'])
                    ->where('channel', 'sms')
                    ->where('locale', $template['locale'])
                    ->whereNull('deleted_at')
                    ->update([
                        'body' => $template['body'],
                        'description' => $template['description'],
                        'updated_at' => $now,
                    ]);
                continue;
            }

            DB::table('notification_templates')->insert([
                'tenant_id' => 1,
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
        DB::table('notification_templates')
            ->where('key', 'ticket_called_sms')
            ->where('tenant_id', 1)
            ->delete();
    }
};
