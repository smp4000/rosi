<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gutscheinnummern muessen nur pro Station eindeutig sein.
     * Gleiche Nummern an verschiedenen Tankstellen (z.B. Fulda + Petersberg) sind erlaubt.
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // Alten globalen Unique-Index entfernen
            $table->dropUnique('vouchers_voucher_number_unique');

            // Neuer zusammengesetzter Unique-Index: Nummer + Station
            $table->unique(['voucher_number', 'station_id'], 'vouchers_number_station_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropUnique('vouchers_number_station_unique');
            $table->unique('voucher_number', 'vouchers_voucher_number_unique');
        });
    }
};
