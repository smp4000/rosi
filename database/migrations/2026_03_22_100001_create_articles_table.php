<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Artikel-Tabelle erstellen.
     * Tenant-Isolation ueber gas_stations.tenant_id (kein eigenes tenant_id).
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->foreignUuid('gas_station_id')->constrained('gas_stations')->cascadeOnDelete()->comment('Zugeordnete Tankstelle');
            $table->unsignedBigInteger('article_group_id')->nullable()->comment('FK auf article_groups');
            $table->bigInteger('article_number')->comment('Artikelnummer aus CSV');

            // Artikeldaten
            $table->string('name', 255)->nullable()->comment('Artikelbezeichnung');
            $table->string('unit', 50)->nullable()->comment('Einheit (St, l, kg, etc.)');
            $table->decimal('purchasing_price', 10, 3)->default(0.000)->comment('Einkaufspreis EUR');
            $table->decimal('selling_price', 8, 2)->default(0.00)->comment('Verkaufspreis EUR');

            // Lieferant
            $table->integer('supplier_number')->nullable()->comment('Hauptlieferant-Nummer');
            $table->string('supplier_name', 255)->nullable()->comment('Lieferant-Name');
            $table->string('order_number', 255)->nullable()->comment('Bestellnummer');
            $table->string('quantity_factor', 50)->nullable()->comment('Mengenfaktor');

            // Bestand
            $table->integer('min_stock')->default(0)->comment('Mindestbestand');
            $table->integer('max_stock')->default(1)->comment('Hoechstbestand');
            $table->integer('current_stock')->default(0)->comment('Istbestand');
            $table->tinyInteger('modus')->default(0)->comment('Bestellmodus (0=berechnen, 1=fester Wert, 2=nie vorschlagen, 3=keine Bestellung)');

            // Status und Import-Info
            $table->enum('status', ['active', 'new', 'price_changed', 'inactive', 'deleted'])->default('new')->comment('Artikelstatus');
            $table->dateTime('csv_printed_at')->nullable()->comment('Druckdatum der CSV-Quelldatei');
            $table->dateTime('imported_at')->nullable()->comment('Zeitpunkt des letzten Imports');

            $table->timestamps();
            $table->softDeletes();

            // Indizes
            $table->unique(['article_number', 'gas_station_id'], 'articles_number_station_unique');
            $table->index('article_group_id');
            $table->index('status');

            // Foreign Keys
            $table->foreign('article_group_id')->references('id')->on('article_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
