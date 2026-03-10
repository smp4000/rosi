<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entfernt die alte 'brand' Text-Spalte, die mit der brand() BelongsTo-Relation kollidiert.
 * Brand-Zuordnung laeuft jetzt ueber brand_id (FK auf brands-Tabelle).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            $table->dropColumn('brand');
        });
    }

    public function down(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('brand_id');
        });
    }
};
