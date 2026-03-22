<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Artikel-EAN-Tabelle erstellen.
     * Jeder Artikel kann mehrere EAN-Codes haben.
     * UNIQUE: EAN + gas_station_id (pro Tankstelle eindeutig).
     */
    public function up(): void
    {
        Schema::create('article_eans', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->foreignUuid('gas_station_id')->constrained('gas_stations')->cascadeOnDelete()->comment('Zugeordnete Tankstelle');
            $table->bigInteger('article_number')->comment('Artikelnummer (FK logisch auf articles)');
            $table->string('ean', 20)->comment('EAN/GTIN-Code');

            // Preisdaten
            $table->decimal('selling_price', 10, 2)->default(0.00)->comment('VK an EAN (Preis an EAN)');
            $table->decimal('base_quantity', 10, 3)->nullable()->comment('Basismenge');
            $table->decimal('reference_quantity', 10, 3)->nullable()->comment('Referenzmenge');
            $table->string('base_unit', 20)->nullable()->comment('Basiseinheit');
            $table->string('quantity_factor', 50)->nullable()->comment('Mengenfaktor');
            $table->string('sales_packaging', 255)->nullable()->comment('Verkaufsgebinde');

            // Import-Info
            $table->dateTime('csv_printed_at')->nullable()->comment('Druckdatum der CSV-Quelldatei');
            $table->dateTime('imported_at')->nullable()->comment('Zeitpunkt des letzten Imports');

            $table->timestamps();
            $table->softDeletes();

            // Unique: EAN pro Tankstelle
            $table->unique(['ean', 'gas_station_id'], 'article_eans_ean_station_unique');
            $table->index('article_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_eans');
    }
};
