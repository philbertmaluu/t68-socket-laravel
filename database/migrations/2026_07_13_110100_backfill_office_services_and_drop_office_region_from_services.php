<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfill office_services from services.office_id/region_id, then drop those columns.
     */
    public function up(): void
    {
        if (!Schema::hasTable('services') || !Schema::hasTable('office_services')) {
            return;
        }

        $regionNames = [];
        try {
            $regionNames = DB::table('hrpd.region')
                ->select(['region_id', 'region_name'])
                ->get()
                ->mapWithKeys(fn ($row) => [(string) $row->region_id => (string) ($row->region_name ?? '')])
                ->all();
        } catch (\Throwable) {
            // HRPD may be unavailable in some environments; leave region_name null.
        }

        $officeNames = [];
        try {
            $officeNames = DB::table('hrpd.office')
                ->select(['office_id', 'office_name'])
                ->get()
                ->mapWithKeys(fn ($row) => [(string) $row->office_id => (string) ($row->office_name ?? '')])
                ->all();
        } catch (\Throwable) {
            // Same fallback for office names.
        }

        $services = DB::table('services')
            ->whereNull('deleted_at')
            ->whereNotNull('office_id')
            ->get(['id', 'tenant_id', 'name', 'office_id', 'region_id', 'created_at', 'updated_at']);

        foreach ($services as $service) {
            $officeId = trim((string) ($service->office_id ?? ''));
            if ($officeId === '') {
                continue;
            }

            $serviceId = (int) $service->id;
            $exists = DB::table('office_services')
                ->where('office_id', $officeId)
                ->where('service_id', $serviceId)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                continue;
            }

            $regionId = $service->region_id !== null ? (string) $service->region_id : null;

            DB::table('office_services')->insert([
                'tenant_id' => $service->tenant_id,
                'office_id' => $officeId,
                'office_name' => $officeNames[$officeId] ?? null,
                'region_id' => $regionId,
                'region_name' => $regionId !== null ? ($regionNames[$regionId] ?? null) : null,
                'service_id' => $serviceId,
                'service_name' => (string) $service->name,
                'created_at' => $service->created_at ?? now(),
                'updated_at' => $service->updated_at ?? now(),
            ]);
        }

        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'office_id')) {
                try {
                    $table->dropIndex('idx_services_office_id');
                } catch (\Throwable) {
                    // Index may already be absent on some environments.
                }
                $table->dropColumn('office_id');
            }
        });

        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'region_id')) {
                try {
                    $table->dropIndex('idx_services_region_id');
                } catch (\Throwable) {
                    // Index may already be absent on some environments.
                }
                $table->dropColumn('region_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (!Schema::hasColumn('services', 'region_id')) {
                $table->string('region_id', 50)->nullable();
                $table->index('region_id', 'idx_services_region_id');
            }
            if (!Schema::hasColumn('services', 'office_id')) {
                $table->string('office_id', 50)->nullable();
                $table->index('office_id', 'idx_services_office_id');
            }
        });

        if (!Schema::hasTable('office_services')) {
            return;
        }

        $assignments = DB::table('office_services')->whereNull('deleted_at')->get();
        foreach ($assignments as $row) {
            DB::table('services')
                ->where('id', $row->service_id)
                ->update([
                    'office_id' => $row->office_id,
                    'region_id' => $row->region_id,
                ]);
        }
    }
};
