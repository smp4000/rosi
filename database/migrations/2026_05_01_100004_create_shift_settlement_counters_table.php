<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zaehlerstaende pro Schichtabrechnung (Waschanlage, Kaffee, Pfand, Hermes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_settlement_counters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shift_settlement_id')->constrained('shift_settlements')->cascadeOnDelete();

            $table->string('counter_type', 30)->comment('waschanlage, kaffee, pfand, hermes, custom');
            $table->string('counter_label')->nullable()->comment('Bezeichnung fuer Custom-Zaehler');

            $table->decimal('value_start', 10, 2)->default(0)->comment('Zaehlerstand Anfang');
            $table->decimal('value_end', 10, 2)->default(0)->comment('Zaehlerstand Ende');
            $table->decimal('value_used', 10, 2)->default(0)->comment('Verbrauch (end - start)');

            $table->timestamps();

            $table->index('shift_settlement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_settlement_counters');
    }
};
