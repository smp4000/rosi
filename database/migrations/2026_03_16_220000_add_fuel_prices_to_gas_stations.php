<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fuegt eigene Kraftstoffpreise zur gas_stations Tabelle hinzu.
 * Damit kann die eigene Tankstelle ihre aktuellen Preise speichern
 * und im Kartenvergleich mit Wettbewerbern anzeigen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            $table->decimal('price_super', 5, 3)->nullable()->after('competitors');
            $table->decimal('price_e10', 5, 3)->nullable()->after('price_super');
            $table->decimal('price_diesel', 5, 3)->nullable()->after('price_e10');
            $table->timestamp('prices_updated_at')->nullable()->after('price_diesel');
        });
    }

    public function down(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            $table->dropColumn(['price_super', 'price_e10', 'price_diesel', 'prices_updated_at']);
        });
    }
};
