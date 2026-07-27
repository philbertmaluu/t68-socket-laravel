<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Create migration already applies slim indexes.
 * Kept as an empty migration so environments that partially ran this file can finish cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty — indexes live in 2026_07_28_000001.
    }

    public function down(): void
    {
        // no-op
    }
};
