<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Warenruecknahmen und Stornos pro Schichtabrechnung.
 * Belege werden fotografiert und mit dem Datensatz verknuepft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_settlement_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shift_settlement_id')->constrained('shift_settlements')->cascadeOnDelete();

            $table->string('receipt_number')->nullable()->comment('Belegnummer');
            $table->time('time')->comment('Uhrzeit der Ruecknahme');
            $table->string('product')->comment('Artikelbezeichnung');
            $table->string('reason')->comment('Begruendung');
            $table->decimal('amount', 10, 2)->comment('Betrag in EUR');
            $table->string('photo')->nullable()->comment('Pfad zum Bon-Foto');

            $table->timestamps();

            $table->index('shift_settlement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_settlement_returns');
    }
};
