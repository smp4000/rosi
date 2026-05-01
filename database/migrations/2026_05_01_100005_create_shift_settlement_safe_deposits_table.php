<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tresor-Einlagen / Safebag-Abschoepfungen pro Schichtabrechnung.
 * Fuer jede Einlage wird ein DYMO-Etikett gedruckt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_settlement_safe_deposits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shift_settlement_id')->constrained('shift_settlements')->cascadeOnDelete();

            $table->decimal('amount', 10, 2)->comment('Einlagebetrag in EUR');
            $table->boolean('has_coins')->default(false)->comment('Kleingeld enthalten?');
            $table->dateTime('deposited_at')->comment('Zeitpunkt der Einlage');
            $table->boolean('label_printed')->default(false)->comment('Etikett wurde gedruckt');
            $table->string('barcode')->nullable()->comment('Barcode/QR-Code auf dem Etikett');

            $table->timestamps();

            $table->index('shift_settlement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_settlement_safe_deposits');
    }
};
