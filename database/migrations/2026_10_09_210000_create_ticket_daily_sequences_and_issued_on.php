<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        Schema::table('tickets', function (Blueprint $table) {
            $table->date('issued_on')->nullable();
        });

        $this->backfillIssuedOn();

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique('idx_tickets_tenant_ticket_unique');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->unique(
                ['tenant_id', 'office_id', 'issued_on', 'ticket_number'],
                'idx_tickets_office_day_number'
            );
            $table->index(['office_id', 'issued_on'], 'idx_tickets_office_issued_on');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique('idx_tickets_office_day_number');
            $table->dropIndex('idx_tickets_office_issued_on');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->unique(['tenant_id', 'ticket_number'], 'idx_tickets_tenant_ticket_unique');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('issued_on');
        });

        Schema::dropIfExists('ticket_daily_sequences');
    }

    private function backfillIssuedOn(): void
    {
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
};
