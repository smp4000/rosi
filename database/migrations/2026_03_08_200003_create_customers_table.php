<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kunden-Tabelle erstellen (B2B + B2C CRM).
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('customer_number', 50)->comment('Eindeutige Kundennummer');
            $table->string('customer_type', 10)->default('b2c')->comment('b2b, b2c');

            // --- B2B Felder ---
            $table->string('company_name')->nullable()->comment('Firmenname');
            $table->string('vat_id', 50)->nullable()->comment('USt-IdNr');
            $table->string('trade_register', 100)->nullable()->comment('Handelsregisternummer');
            $table->string('industry', 100)->nullable()->comment('Branche');
            $table->string('company_size', 50)->nullable()->comment('Unternehmensgroesse');
            $table->string('website')->nullable();

            // --- Kontaktperson / B2C Person ---
            $table->string('salutation', 20)->nullable()->comment('herr, frau, divers');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('position', 100)->nullable()->comment('Position/Funktion (B2B)');
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('fax', 50)->nullable();

            // --- Adresse ---
            $table->string('street')->nullable();
            $table->string('zip', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('DE');

            // --- CRM Felder ---
            $table->string('status', 20)->default('active')->comment('lead, active, inactive, blocked');
            $table->string('source', 100)->nullable()->comment('Herkunft (Empfehlung, Website, etc.)');
            $table->string('payment_terms', 100)->nullable()->comment('Zahlungsbedingungen');
            $table->decimal('credit_limit', 10, 2)->nullable()->comment('Kreditlimit');
            $table->decimal('discount_rate', 5, 2)->nullable()->comment('Rabatt in %');
            $table->string('customer_card_number', 50)->nullable()->comment('Kundenkartennummer');
            $table->unsignedInteger('loyalty_points')->default(0)->comment('Bonuspunkte');
            $table->json('tags')->nullable()->comment('Schlagwoerter');
            $table->text('notes')->nullable();
            $table->timestamp('last_contact_at')->nullable()->comment('Letzter Kontakt');
            $table->timestamp('next_followup_at')->nullable()->comment('Naechste Wiedervorlage');
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete()->comment('Zustaendiger Mitarbeiter');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'customer_number']);
        });

        // Pivot: Kunden <-> Tankstelle
        Schema::create('customer_gas_station', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('gas_station_id')->constrained('gas_stations')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['customer_id', 'gas_station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_gas_station');
        Schema::dropIfExists('customers');
    }
};
