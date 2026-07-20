<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MoodDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach (['en', 'sw'] as $locale) {
            $exists = DB::table('mood_configurations')
                ->whereNull('tenant_id')
                ->where('locale', $locale)
                ->exists();

            if (!$exists) {
                DB::table('mood_configurations')->insert([
                    'tenant_id' => null,
                    'locale' => $locale,
                    'theme' => json_encode([
                        'primary_color' => '#1B4D89',
                        'secondary_color' => '#2E7D32',
                        'gradient_start' => '#0F2027',
                        'gradient_end' => '#2C5364',
                        'glass_opacity' => 0.15,
                        'background_animation' => 'gradient_mesh',
                    ]),
                    'company' => json_encode([
                        'name' => 'NSSF',
                        'logo_url' => null,
                    ]),
                    'messages' => json_encode($locale === 'sw' ? [
                        'idle_prompt' => 'Uzoefu wako ulikuwaje leo?',
                        'thank_you' => 'Asante kwa maoni yako.',
                        'reason_prompt' => 'Tunaweza kuboresha nini?',
                        'session_expired' => 'Kipindi kimeisha. Asante.',
                    ] : [
                        'idle_prompt' => 'How was your experience today?',
                        'thank_you' => 'Thank you for your feedback.',
                        'reason_prompt' => 'What can we improve?',
                        'session_expired' => 'Session expired. Thank you.',
                    ]),
                    'timeouts' => json_encode([
                        'thank_you_seconds' => 5,
                        'counter_session_seconds' => 30,
                        'heartbeat_seconds' => 30,
                    ]),
                    'features' => json_encode([
                        'survey' => false,
                        'nps' => false,
                        'qr_feedback' => false,
                        'voice_feedback' => false,
                    ]),
                    'version' => 1,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $ratings = [
            ['key' => 'excellent', 'title' => 'Excellent', 'emoji' => '😍', 'color' => '#2E7D32', 'score' => 5],
            ['key' => 'good', 'title' => 'Good', 'emoji' => '🙂', 'color' => '#558B2F', 'score' => 4],
            ['key' => 'average', 'title' => 'Average', 'emoji' => '😐', 'color' => '#F9A825', 'score' => 3],
            ['key' => 'poor', 'title' => 'Poor', 'emoji' => '🙁', 'color' => '#EF6C00', 'score' => 2],
            ['key' => 'very_poor', 'title' => 'Very Poor', 'emoji' => '😡', 'color' => '#C62828', 'score' => 1],
        ];

        foreach ($ratings as $index => $rating) {
            $exists = DB::table('mood_rating_options')
                ->whereNull('tenant_id')
                ->where('key', $rating['key'])
                ->where('locale', 'en')
                ->exists();

            if (!$exists) {
                DB::table('mood_rating_options')->insert([
                    'tenant_id' => null,
                    'key' => $rating['key'],
                    'title' => $rating['title'],
                    'emoji' => $rating['emoji'],
                    'color' => $rating['color'],
                    'score' => $rating['score'],
                    'locale' => 'en',
                    'sort_order' => $index + 1,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $reasons = [
            ['key' => 'waiting_time', 'title' => 'Waiting Time'],
            ['key' => 'staff_attitude', 'title' => 'Staff Attitude'],
            ['key' => 'service_quality', 'title' => 'Service Quality'],
            ['key' => 'system_delay', 'title' => 'System Delay'],
            ['key' => 'office_environment', 'title' => 'Office Environment'],
            ['key' => 'other', 'title' => 'Other'],
        ];

        foreach ($reasons as $index => $reason) {
            $exists = DB::table('mood_feedback_reasons')
                ->whereNull('tenant_id')
                ->where('key', $reason['key'])
                ->where('locale', 'en')
                ->exists();

            if (!$exists) {
                DB::table('mood_feedback_reasons')->insert([
                    'tenant_id' => null,
                    'key' => $reason['key'],
                    'title' => $reason['title'],
                    'category' => 'general',
                    'applies_to_ratings' => json_encode(['poor', 'very_poor']),
                    'locale' => 'en',
                    'sort_order' => $index + 1,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
