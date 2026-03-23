<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rechnungen aus ZUGFeRD-PDF-Import.
     * Verknuepft mit Firmenkunde und Tankstelle.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->foreignUuid('gas_station_id')->nullable()->constrained('gas_stations')->nullOnDelete()->comment('Tankstelle');
            $table->foreignUuid('batch_id')->nullable()->constrained('invoice_batches')->nullOnDelete()->comment('Import-Batch');
            $table->foreignUuid('corporate_customer_id')->nullable()->constrained('corporate_customers')->nullOnDelete()->comment('Firmenkunde');

            // Rechnungsdaten
            $table->string('invoice_number', 50)->comment('Rechnungsnummer');
            $table->date('invoice_date')->comment('Rechnungsdatum');
            $table->decimal('amount', 10, 2)->default(0)->comment('Bruttobetrag');
            $table->decimal('net_amount', 10, 2)->nullable()->comment('Nettobetrag');
            $table->decimal('tax_amount', 10, 2)->nullable()->comment('MwSt-Betrag');

            // PDF + ZUGFeRD
            $table->string('pdf_path', 500)->nullable()->comment('Pfad zur PDF-Datei in Storage');
            $table->json('zugferd_data')->nullable()->comment('Komplette ZUGFeRD-XML-Daten als JSON');

            // Status
            $table->enum('status', ['uploaded', 'processed', 'sent', 'printed', 'failed'])->default('uploaded')->comment('Rechnungsstatus');
            $table->enum('import_status', ['success', 'error', 'duplicate'])->nullable()->comment('Import-Ergebnis');
            $table->text('import_error_message')->nullable()->comment('Fehlermeldung beim Import');

            // Versand
            $table->dateTime('sent_at')->nullable()->comment('E-Mail versendet am');
            $table->dateTime('printed_at')->nullable()->comment('Gedruckt am');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['invoice_number', 'gas_station_id'], 'invoices_number_station_idx');
            $table->index('status');
            $table->index('corporate_customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
