<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $kioskTheme = json_encode([
            'primary_color' => '#902D30',
            'secondary_color' => '#2E7D32',
            'accent_color' => '#EAB308',
            'gold_color' => '#C9A227',
            'gradient_start' => '#902D30',
            'gradient_end' => '#5C1820',
            'glass_opacity' => 0.18,
            'background_animation' => 'gradient_mesh',
        ]);

        DB::table('mood_configurations')->update(['theme' => $kioskTheme]);
    }

    public function down(): void
    {
        // No rollback — theme is cosmetic.
    }
};
