<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Import-Protokoll fuer CSV-Artikel-Imports.
     * Speichert Zusammenfassung jedes Imports.
     */
    public function up(): void
    {
        Schema::create('article_imports', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->foreignUuid('gas_station_id')->constrained('gas_stations')->cascadeOnDelete()->comment('Importierte Tankstelle');

            // CSV-Metadaten
            $table->string('station_number', 50)->comment('Stationsnummer aus CSV-Datei');
            $table->dateTime('csv_printed_at')->comment('Druckdatum der CSV-Datei');
            $table->string('filename', 255)->comment('Original-Dateiname');

            // Import-Zusammenfassung
            $table->unsignedInteger('articles_total')->default(0)->comment('Gesamt-Artikel in CSV');
            $table->unsignedInteger('articles_created')->default(0)->comment('Neu angelegte Artikel');
            $table->unsignedInteger('articles_updated')->default(0)->comment('Aktualisierte Artikel (Preisaenderungen)');
            $table->unsignedInteger('articles_unchanged')->default(0)->comment('Unveraenderte Artikel');

            // Status
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->comment('Import-Status');
            $table->text('error_message')->nullable()->comment('Fehlermeldung bei gescheitertem Import');

            // Wer hat importiert
            $table->uuid('imported_by')->nullable()->comment('User der den Import ausgeloest hat');
            $table->foreign('imported_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_imports');
    }
};
