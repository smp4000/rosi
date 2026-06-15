<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * print_jobs zur echten Druck-Warteschlange ausbauen.
     *
     * Neu:
     * - station_id   : Job gehoert zu einer Station -> der Agent dieser Station holt ihn
     * - printer_name : Ziel-DYMO (aus Station-Mapping); null = Default-Drucker des Agents
     * - agent_id     : welcher Agent den Job geholt hat (printing)
     * - expires_at   : TTL — danach wird der Job nicht mehr gedruckt (-> expired)
     * - error_message, attempts : Fehler-Feedback + Retry-Zaehler
     * - reference_type : strukturierte Herkunft (voucher | fuel_theft | shift_deposit | ...)
     *
     * status erweitert um 'expired'.
     */
    public function up(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->uuid('station_id')->nullable()->after('tenant_id');
            $table->string('printer_name')->nullable()->after('reference');
            $table->uuid('agent_id')->nullable()->after('printer_name');
            $table->string('reference_type', 50)->nullable()->after('reference');
            $table->timestamp('expires_at')->nullable()->after('status');
            $table->text('error_message')->nullable()->after('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0)->after('error_message');

            $table->foreign('station_id')->references('id')->on('gas_stations')->nullOnDelete();

            $table->index(['station_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('print_jobs', function (Blueprint $table) {
            $table->dropForeign(['station_id']);
            $table->dropIndex(['station_id', 'status']);
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn([
                'station_id', 'printer_name', 'agent_id', 'reference_type',
                'expires_at', 'error_message', 'attempts',
            ]);
        });
    }
};
