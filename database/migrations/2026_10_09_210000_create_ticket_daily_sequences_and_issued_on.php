<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ticket_daily_sequences')) {
            Schema::create('ticket_daily_sequences', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('office_id', 50);
                $table->date('issued_on');
                $table->unsignedInteger('last_value')->default(0);
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->unique(['tenant_id', 'office_id', 'issued_on'], 'idx_ticket_daily_seq_unique');
                $table->index(['office_id', 'issued_on'], 'idx_ticket_daily_seq_office_day');
            });
        }

        if (Schema::hasTable('tickets') && !Schema::hasColumn('tickets', 'issued_on')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->date('issued_on')->nullable();
            });
        }

        $this->backfillIssuedOn();
        $this->dropLegacyTenantTicketUnique();

        if (!$this->hasNamedIndex('tickets', 'idx_tickets_office_day_number')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->unique(
                    ['tenant_id', 'office_id', 'issued_on', 'ticket_number'],
                    'idx_tickets_office_day_number'
                );
            });
        }

        if (!$this->hasNamedIndex('tickets', 'idx_tickets_office_issued_on')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->index(['office_id', 'issued_on'], 'idx_tickets_office_issued_on');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasNamedIndex('tickets', 'idx_tickets_office_day_number')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropUnique('idx_tickets_office_day_number');
            });
        }

        if ($this->hasNamedIndex('tickets', 'idx_tickets_office_issued_on')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropIndex('idx_tickets_office_issued_on');
            });
        }

        if (!$this->hasNamedIndex('tickets', 'idx_tickets_tenant_ticket_unique')
            && !$this->hasNamedIndex('tickets', 'idx_tickets_tenant_ticket_uniq')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->unique(['tenant_id', 'ticket_number'], 'idx_tickets_tenant_ticket_unique');
            });
        }

        if (Schema::hasColumn('tickets', 'issued_on')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropColumn('issued_on');
            });
        }

        Schema::dropIfExists('ticket_daily_sequences');
    }

    private function backfillIssuedOn(): void
    {
        if (!Schema::hasTable('tickets') || !Schema::hasColumn('tickets', 'issued_on')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'oracle') {
            DB::statement("UPDATE tickets SET issued_on = TRUNC(created_at + NUMTODSINTERVAL(3, 'HOUR')) WHERE issued_on IS NULL");
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("UPDATE tickets SET issued_on = date(datetime(created_at, '+3 hours')) WHERE issued_on IS NULL");
            return;
        }

        DB::statement("UPDATE tickets SET issued_on = DATE(CONVERT_TZ(created_at, '+00:00', '+03:00')) WHERE issued_on IS NULL");
    }

    /**
     * Oracle may store the unique as an index (not a constraint), under the full
     * name, or truncated to 30 characters. Drop by column list so a missing name
     * does not abort a partially applied migrate.
     */
    private function dropLegacyTenantTicketUnique(): void
    {
        if (Schema::getConnection()->getDriverName() === 'oracle') {
            $this->dropOracleUniqueOnColumns('TICKETS', ['TENANT_ID', 'TICKET_NUMBER']);
            return;
        }

        foreach (['idx_tickets_tenant_ticket_unique', 'idx_tickets_tenant_ticket_uniq'] as $name) {
            if (!$this->hasNamedIndex('tickets', $name)) {
                continue;
            }

            try {
                Schema::table('tickets', function (Blueprint $table) use ($name) {
                    $table->dropUnique($name);
                });
            } catch (\Throwable) {
                // Already gone or stored under a different object type.
            }
        }
    }

    private function dropOracleUniqueOnColumns(string $table, array $columns): void
    {
        $wanted = implode(',', $columns);

        $constraints = DB::select(
            "SELECT constraint_name FROM user_constraints
             WHERE table_name = ? AND constraint_type = 'U'",
            [$table]
        );

        foreach ($constraints as $row) {
            $name = (string) $row->constraint_name;
            $actual = DB::select(
                "SELECT column_name FROM user_cons_columns
                 WHERE table_name = ? AND constraint_name = ?
                 ORDER BY position",
                [$table, $name]
            );
            $list = implode(',', array_map(
                fn ($col) => strtoupper((string) $col->column_name),
                $actual
            ));

            if ($list !== $wanted) {
                continue;
            }

            DB::statement('ALTER TABLE "'.$table.'" DROP CONSTRAINT "'.$name.'"');
        }

        $indexes = DB::select(
            "SELECT index_name FROM user_indexes
             WHERE table_name = ? AND uniqueness = 'UNIQUE'",
            [$table]
        );

        foreach ($indexes as $row) {
            $name = (string) $row->index_name;
            $actual = DB::select(
                "SELECT column_name FROM user_ind_columns
                 WHERE table_name = ? AND index_name = ?
                 ORDER BY column_position",
                [$table, $name]
            );
            $list = implode(',', array_map(
                fn ($col) => strtoupper((string) $col->column_name),
                $actual
            ));

            if ($list !== $wanted) {
                continue;
            }

            try {
                DB::statement('DROP INDEX "'.$name.'"');
            } catch (\Throwable) {
                // Constraint drop above may already have removed the backing index.
            }
        }
    }

    private function hasNamedIndex(string $table, string $name): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'oracle') {
            return Schema::hasIndex($table, $name);
        }

        $found = DB::selectOne(
            'SELECT index_name FROM user_indexes
             WHERE table_name = UPPER(?) AND UPPER(index_name) = UPPER(?)',
            [$table, $name]
        );

        return $found !== null;
    }
};
