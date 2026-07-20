<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\MoodDefaultsSeeder', '--force' => true]);
    }

    public function down(): void
    {
        // Defaults are safe to keep; no rollback required.
    }
};
