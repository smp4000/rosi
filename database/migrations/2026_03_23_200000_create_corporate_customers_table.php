<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Firmenkunden-Tabelle.
     * Gleiche Kundennummer kann bei verschiedenen Tankstellen existieren.
     * IBAN/BIC/Bank verschluesselt (DSGVO).
     */
    public function up(): void
    {
        Schema::create('corporate_customers', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->foreignUuid('gas_station_id')->constrained('gas_stations')->cascadeOnDelete()->comment('Zugeordnete Tankstelle');

            // Stammdaten
            $table->integer('customer_number')->comment('Firmennummer aus Kassensystem');
            $table->string('salutation', 50)->nullable()->comment('Anrede (Herr, Frau, Firma, etc.)');
            $table->string('name', 255)->comment('Firmenname oder Personenname');
            $table->string('name_2', 255)->nullable()->comment('Zweite Namenszeile');
            $table->string('department', 255)->nullable()->comment('Abteilung oder Zusatzbezeichnung');

            // Adresse
            $table->string('street', 255)->nullable()->comment('Strasse + Hausnummer');
            $table->string('zip', 10)->nullable()->comment('Postleitzahl');
            $table->string('city', 255)->nullable()->comment('Stadt');
            $table->string('phone', 100)->nullable()->comment('Telefonnummer');
            $table->string('email', 255)->default('keine-email@platzhalter.local')->comment('E-Mail fuer Rechnungsversand');

            // Bankdaten (DSGVO-verschluesselt)
            $table->text('iban')->nullable()->comment('IBAN (verschluesselt)');
            $table->text('bic')->nullable()->comment('BIC (verschluesselt)');
            $table->text('bank_name')->nullable()->comment('Bankname (verschluesselt)');
            $table->string('mandate_reference', 100)->nullable()->comment('SEPA-Mandatsreferenz');
            $table->string('contract_number', 100)->nullable()->comment('Vertragsnummer');

            // Konditionen
            $table->string('payment_method', 50)->default('Bar')->comment('Zahlart: Bar, SEPA - Basislastschrift, SEPA - Firmenlastschrift');
            $table->decimal('discount_percent', 5, 2)->default(0.00)->comment('Skonto in Prozent');
            $table->decimal('credit_limit', 10, 2)->default(0.00)->comment('Kreditlimit in EUR');
            $table->decimal('prepayment', 10, 2)->default(0.00)->comment('Vorauszahlung in EUR');
            $table->boolean('print_delivery_note')->default(false)->comment('Lieferschein drucken');
            $table->boolean('print_prepayment')->default(false)->comment('Vorauszahlung drucken');

            // Sonstiges
            $table->string('notes', 500)->nullable()->comment('Interne Notizen');

            // Import-Metadaten
            $table->dateTime('csv_printed_at')->nullable()->comment('Druckdatum der CSV-Quelldatei');
            $table->dateTime('imported_at')->nullable()->comment('Zeitpunkt des letzten Imports');

            $table->timestamps();
            $table->softDeletes();

            // Unique: Kundennummer pro Tankstelle
            $table->unique(['customer_number', 'gas_station_id'], 'corp_cust_number_station_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corporate_customers');
    }
};
