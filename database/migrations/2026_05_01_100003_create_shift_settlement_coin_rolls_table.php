<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Muenzrollen-Bestand pro Schichtabrechnung (Wechselgeld).
 * Eine Zeile pro Stueckelung (2€, 1€, 50ct, 20ct, 10ct, 5ct, 2ct, 1ct).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_settlement_coin_rolls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shift_settlement_id')->constrained('shift_settlements')->cascadeOnDelete();

            $table->unsignedInteger('denomination')->comment('Nennwert in Cent: 200, 100, 50, 20, 10, 5, 2, 1');
            $table->decimal('roll_value', 8, 2)->comment('Wert pro Rolle: 50.00, 25.00, 20.00, 8.00, 4.00, 2.50, 1.00, 0.50');

            // Anzahl Rollen
            $table->unsignedInteger('count_start')->default(0)->comment('Anfangsbestand (Anzahl Rollen)');
            $table->unsignedInteger('count_end')->default(0)->comment('Endbestand (Anzahl Rollen)');

            // Werte (berechnet: Anzahl × Rollenwert)
            $table->decimal('value_start', 10, 2)->default(0)->comment('Anfangswert in EUR');
            $table->decimal('value_end', 10, 2)->default(0)->comment('Endwert in EUR');
            $table->decimal('value_used', 10, 2)->default(0)->comment('Verbrauch in EUR (start - end)');

            $table->timestamps();

            $table->index('shift_settlement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_settlement_coin_rolls');
    }
};
