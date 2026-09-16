<?php

namespace Database\Seeders;

use App\Domains\Service\Models\Service;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the services catalog table only (does not touch office_services).
     *
     * Safe to re-run in production:
     *   php artisan db:seed --class=ServiceSeeder --force
     *
     * IDs match the NSSF QMS catalog (ID 10 intentionally absent).
     */
    public function run(): void
    {
        $tenant = DB::table('tenants')->orderBy('id')->first();

        if (!$tenant) {
            $this->command?->warn('No tenant found. Please run TenantSeeder first.');
            return;
        }

        $tenantId = $tenant->id;
        app()->instance('tenant.id', $tenantId);

        $services = [
            [
                'id' => 1,
                'name' => 'Claim Lodging',
                'description' => 'Kufungua Madai',
                'swahili_name' => 'Kufungua Madai',
                'estimated_time' => 30,
            ],
            [
                'id' => 2,
                'name' => 'Customer Service',
                'description' => 'Huduma Kwa Wateja',
                'swahili_name' => 'Huduma Kwa Wateja',
                'estimated_time' => 20,
            ],
            [
                'id' => 3,
                'name' => 'Response to Queries',
                'description' => 'Majibu ya Hoja Mbali Mbali',
                'swahili_name' => 'Majibu ya Hoja Mbali Mbali',
                'estimated_time' => 25,
            ],
            [
                'id' => 4,
                'name' => 'Under Payment',
                'description' => 'Mapunjo',
                'swahili_name' => 'Mapunjo',
                'estimated_time' => 30,
            ],
            [
                'id' => 5,
                'name' => 'Claim Follow-up',
                'description' => 'Ufuatiliaji',
                'swahili_name' => 'Ufuatiliaji',
                'estimated_time' => 20,
            ],
            [
                'id' => 6,
                'name' => 'Receipting',
                'description' => 'Risiti',
                'swahili_name' => 'Risiti',
                'estimated_time' => 15,
            ],
            [
                'id' => 7,
                'name' => 'SHIB',
                'description' => 'Matibabu',
                'swahili_name' => 'Matibabu',
                'estimated_time' => 30,
            ],
            [
                'id' => 8,
                'name' => 'Problematic Claims',
                'description' => 'Madai yenye Shida',
                'swahili_name' => 'Madai yenye Shida',
                'estimated_time' => 45,
            ],
            [
                'id' => 9,
                'name' => 'Registration',
                'description' => 'Usajili',
                'swahili_name' => 'Usajili',
                'estimated_time' => 25,
            ],
            [
                'id' => 11,
                'name' => 'Claim Identification',
                'description' => 'Utambulisho',
                'swahili_name' => 'Utambulisho',
                'estimated_time' => 20,
            ],
            [
                'id' => 12,
                'name' => 'Special Needs',
                'description' => 'Mahitaji maalum',
                'swahili_name' => 'Mahitaji maalum',
                'estimated_time' => 20,
            ],
            [
                'id' => 13,
                'name' => 'Open Registry',
                'description' => 'Masijala ya wazi',
                'swahili_name' => 'Masijala ya wazi',
                'estimated_time' => 20,
            ],
            [
                'id' => 14,
                'name' => 'Complaints',
                'description' => 'Malalamiko',
                'swahili_name' => 'Malalamiko',
                'estimated_time' => 20,
            ],

        ];

        $created = 0;
        $updated = 0;

        Service::unguarded(function () use ($services, $tenantId, &$created, &$updated) {
            foreach ($services as $row) {
                $model = Service::withoutGlobalScopes()
                    ->withTrashed()
                    ->updateOrCreate(
                        ['id' => $row['id']],
                        [
                            'tenant_id' => $tenantId,
                            'name' => $row['name'],
                            'description' => $row['description'],
                            'swahili_name' => $row['swahili_name'],
                            'estimated_time' => $row['estimated_time'],
                            'status' => 'ACTIVE',
                            'deleted_at' => null,
                            'deleted_by' => null,
                        ]
                    );

                if ($model->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }
        });

        $this->syncOracleIdSequence('services');

        $this->command?->info("Services table upsert complete: {$created} created, {$updated} updated.");
        $this->command?->info('Re-run in prod: php artisan db:seed --class=ServiceSeeder --force');
    }

    /**
     * Explicit IDs leave the Oracle sequence behind; bump it so later inserts do not collide.
     */
    private function syncOracleIdSequence(string $table): void
    {
        if (DB::connection()->getDriverName() !== 'oracle') {
            return;
        }

        $max = (int) (DB::table($table)->max('id') ?? 0);
        if ($max < 1) {
            return;
        }

        $candidates = [
            strtoupper($table) . '_ID_SEQ',
            strtolower($table) . '_id_seq',
        ];

        foreach ($candidates as $seq) {
            try {
                do {
                    $row = DB::selectOne("SELECT {$seq}.NEXTVAL AS n FROM DUAL");
                    $n = (int) ($row->n ?? $row->N ?? 0);
                } while ($n < $max);

                $this->command?->info("Oracle sequence {$seq} is at or above {$max}.");
                return;
            } catch (\Throwable) {
                continue;
            }
        }

        $this->command?->warn("Could not advance {$table} ID sequence after seeding explicit IDs.");
    }
}
