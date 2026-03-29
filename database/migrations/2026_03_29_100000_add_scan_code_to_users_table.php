<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fuegt ein scan_code Feld hinzu fuer Login per Scanner/NFC/QR-Code.
 *
 * Der scan_code ist ein eindeutiger Code pro Mitarbeiter, der auf:
 * - Barcode-Badges gedruckt werden kann (MDE-Scanner)
 * - NFC-Karten/-Chips gespeichert werden kann
 * - Als QR-Code fuer Kamera-Login verwendet werden kann
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('scan_code')->nullable()->unique()->after('pin_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('scan_code');
        });
    }
};
