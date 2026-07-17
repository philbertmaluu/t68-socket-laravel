<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('notification_templates')
            ->where('key', 'ticket_completed_sms')
            ->where('channel', 'sms')
            ->where('locale', 'sw')
            ->update([
                'body' => "Ndugu {memberName},\nHuduma yako kwa tiketi namba {ticketNumber} ({serviceType}) imekamilika.\nTafadhali toa maoni yako: {feedbackUrl}\nAsante kwa kutumia huduma za NSSF.\nKaribu tena!",
                'updated_at' => now(),
            ]);

        DB::table('notification_templates')
            ->where('key', 'ticket_completed_sms')
            ->where('channel', 'sms')
            ->where('locale', 'en')
            ->update([
                'body' => "Dear {memberName},\nYour service for ticket number {ticketNumber} ({serviceType}) has been completed.\nPlease share your feedback: {feedbackUrl}\nThank you for using NSSF services.\nWelcome again!",
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('notification_templates')
            ->where('key', 'ticket_completed_sms')
            ->where('channel', 'sms')
            ->where('locale', 'sw')
            ->update([
                'body' => "Ndugu {memberName},\nHuduma yako kwa tiketi namba {ticketNumber} ({serviceType}) imekamilika.\nAsante kwa kutumia huduma za NSSF.\nKaribu tena!",
                'updated_at' => now(),
            ]);

        DB::table('notification_templates')
            ->where('key', 'ticket_completed_sms')
            ->where('channel', 'sms')
            ->where('locale', 'en')
            ->update([
                'body' => "Dear {memberName},\nYour service for ticket number {ticketNumber} ({serviceType}) has been completed.\nThank you for using NSSF services.\nWelcome again!",
                'updated_at' => now(),
            ]);
    }
};
