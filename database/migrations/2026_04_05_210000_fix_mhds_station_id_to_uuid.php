<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * station_id in mhds von unsignedBigInteger auf UUID aendern.
 * gas_stations nutzt UUIDs, daher muss station_id kompatibel sein.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Alte Daten mit falscher station_id (Integer) loeschen
        \App\Models\Mhd::withTrashed()->whereRaw("LENGTH(station_id) < 30")->forceDelete();

        Schema::table('mhds', function (Blueprint $table) {
            $table->dropIndex(['station_id', 'mhd_date']);
        });

        Schema::table('mhds', function (Blueprint $table) {
            $table->uuid('station_id')->change();
        });

        Schema::table('mhds', function (Blueprint $table) {
            $table->index(['station_id', 'mhd_date']);
        });
    }

    public function down(): void
    {
        Schema::table('mhds', function (Blueprint $table) {
            $table->dropIndex(['station_id', 'mhd_date']);
        });

        Schema::table('mhds', function (Blueprint $table) {
            $table->unsignedBigInteger('station_id')->change();
        });

        Schema::table('mhds', function (Blueprint $table) {
            $table->index(['station_id', 'mhd_date']);
        });
    }
};
