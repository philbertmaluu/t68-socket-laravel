<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('notification_templates')
            ->where('tenant_id', 1)
            ->where('key', 'ticket_called_sms')
            ->where('channel', 'sms')
            ->where('locale', 'sw')
            ->whereNull('deleted_at')
            ->update([
                'body' => 'Ndugu Mteja tiketi yako imeitwa tafadhali elekea {counterTypeName} nambari {counterName} ili kupokea huduma',
                'updated_at' => $now,
            ]);

        DB::table('notification_templates')
            ->where('tenant_id', 1)
            ->where('key', 'ticket_called_sms')
            ->where('channel', 'sms')
            ->where('locale', 'en')
            ->whereNull('deleted_at')
            ->update([
                'body' => 'Dear Customer, your ticket has been called. Please proceed to {counterTypeName} number {counterName} to receive service.',
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        DB::table('notification_templates')
            ->where('tenant_id', 1)
            ->where('key', 'ticket_called_sms')
            ->where('channel', 'sms')
            ->where('locale', 'sw')
            ->whereNull('deleted_at')
            ->update([
                'body' => 'Ndugu {memberName}, tiketi yako namba {ticketNumber} imeitwa. Tafadhali elekea dirisha {counterName} kupokea huduma ya {serviceType}.',
                'updated_at' => now(),
            ]);

        DB::table('notification_templates')
            ->where('tenant_id', 1)
            ->where('key', 'ticket_called_sms')
            ->where('channel', 'sms')
            ->where('locale', 'en')
            ->whereNull('deleted_at')
            ->update([
                'body' => 'Dear {memberName}, your ticket number {ticketNumber} has been called. Please proceed to counter {counterName} for {serviceType}.',
                'updated_at' => now(),
            ]);
    }
};
