<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fuegt die competitors JSON-Spalte fuer Wettbewerbstankstellen hinzu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            $table->json('competitors')->nullable()->after('photos')
                ->comment('Wettbewerbstankstellen (JSON: name, street, distance, notes)');
        });
    }

    public function down(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            $table->dropColumn('competitors');
        });
    }
};
