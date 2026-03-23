<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Import-Batch fuer ZUGFeRD-Rechnungen.
     * Trackt Fortschritt und Statistik eines Imports.
     */
    public function up(): void
    {
        Schema::create('invoice_batches', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->foreignUuid('gas_station_id')->nullable()->constrained('gas_stations')->nullOnDelete()->comment('Tankstelle (falls bekannt)');

            $table->string('name', 255)->comment('Batch-Name (z.B. Import 23.03.2026 18:30)');
            $table->string('source', 50)->default('browser_upload')->comment('Quelle: browser_upload, folder_import');

            // Statistik
            $table->unsignedInteger('total_files')->default(0)->comment('Gesamt hochgeladene Dateien');
            $table->unsignedInteger('processed_count')->default(0)->comment('Bisher verarbeitete Dateien');
            $table->unsignedInteger('success_count')->default(0)->comment('Erfolgreich verarbeitet');
            $table->unsignedInteger('error_count')->default(0)->comment('Fehler bei Verarbeitung');
            $table->unsignedInteger('duplicate_count')->default(0)->comment('Duplikate uebersprungen');
            $table->unsignedInteger('new_customers_count')->default(0)->comment('Neu angelegte Kunden');

            // Status
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            // Wer hat importiert
            $table->uuid('imported_by')->nullable();
            $table->foreign('imported_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_batches');
    }
};
