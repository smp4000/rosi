<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tankkarten/Teilnehmer fuer Firmenkunden.
     * Jeder Firmenkunde kann mehrere Tankkarten haben.
     */
    public function up(): void
    {
        Schema::create('fuel_cards', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->foreignUuid('gas_station_id')->constrained('gas_stations')->cascadeOnDelete()->comment('Zugeordnete Tankstelle');
            $table->foreignUuid('corporate_customer_id')->constrained('corporate_customers')->cascadeOnDelete()->comment('Zugeordneter Firmenkunde');

            // Kartendaten
            $table->integer('card_number')->comment('Teilnehmer-Nr / Kartennummer');
            $table->string('driver_name', 255)->nullable()->comment('Fahrername');
            $table->string('license_plate', 50)->nullable()->comment('Kennzeichen');
            $table->string('notes', 500)->nullable()->comment('Zusatzvermerke');

            // Import-Metadaten
            $table->dateTime('imported_at')->nullable()->comment('Zeitpunkt des letzten Imports');

            $table->timestamps();
            $table->softDeletes();

            // Unique: Kartennummer pro Tankstelle
            $table->unique(['card_number', 'gas_station_id'], 'fuel_cards_number_station_unique');
            $table->index('corporate_customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_cards');
    }
};
