<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drucker-Zuordnung pro Station: Mapping job_type -> printer_name.
     * Beispiel: { "voucher_labels": "DYMO 550 Kasse", "mhd_labels": "DYMO 450 Buero" }
     * Leer/Default-Schluessel "*" greift fuer alle nicht gemappten Typen.
     */
    public function up(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            $table->json('printer_map')->nullable()->after('device_setup_token');
        });
    }

    public function down(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            $table->dropColumn('printer_map');
        });
    }
};
