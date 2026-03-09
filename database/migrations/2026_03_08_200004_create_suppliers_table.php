<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lieferanten-Tabelle mit Preislisten und Reklamationen erstellen.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('supplier_number', 50)->comment('Eindeutige Lieferantennummer');

            // --- Firmendaten ---
            $table->string('company_name')->comment('Firmenname');
            $table->string('vat_id', 50)->nullable()->comment('USt-IdNr');
            $table->string('trade_register', 100)->nullable();
            $table->string('website')->nullable();

            // --- Kontakt ---
            $table->string('contact_salutation', 20)->nullable()->comment('herr, frau, divers');
            $table->string('contact_first_name')->nullable();
            $table->string('contact_last_name')->nullable();
            $table->string('contact_position', 100)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('contact_mobile', 50)->nullable();

            // --- Adresse ---
            $table->string('street')->nullable();
            $table->string('zip', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('DE');

            // --- Lieferanten-Details ---
            $table->string('category', 30)->default('other')->comment('fuel, shop, technology, cleaning, food, other');
            $table->json('supply_types')->nullable()->comment('Liefertypen Array');

            // --- Vertrag ---
            $table->date('contract_start')->nullable()->comment('Vertragsbeginn');
            $table->date('contract_end')->nullable()->comment('Vertragsende');
            $table->boolean('contract_auto_renew')->default(false)->comment('Automatische Verlaengerung');
            $table->string('contract_notice_period', 50)->nullable()->comment('Kuendigungsfrist');
            $table->string('contract_file')->nullable()->comment('Vertragsdokument');
            $table->string('payment_terms', 100)->nullable()->comment('Zahlungsbedingungen');
            $table->decimal('minimum_order', 10, 2)->nullable()->comment('Mindestbestellmenge/-wert');
            $table->json('delivery_days')->nullable()->comment('Liefertage Array');

            // --- Bewertung ---
            $table->unsignedTinyInteger('rating')->nullable()->comment('Bewertung 1-5 Sterne');
            $table->text('rating_notes')->nullable()->comment('Bewertungskommentar');

            // --- CRM ---
            $table->string('status', 20)->default('active')->comment('active, inactive, blocked, prospect');
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamp('next_followup_at')->nullable();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete()->comment('Zustaendiger Mitarbeiter');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'supplier_number']);
        });

        // Pivot: Lieferant <-> Tankstelle
        Schema::create('supplier_gas_station', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignUuid('gas_station_id')->constrained('gas_stations')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['supplier_id', 'gas_station_id']);
        });

        // Preislisten
        Schema::create('supplier_price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('product_name')->comment('Produktbezeichnung');
            $table->string('unit', 50)->comment('Einheit (Liter, Stueck, kg)');
            $table->decimal('price', 10, 4)->comment('Preis pro Einheit');
            $table->date('valid_from')->comment('Gueltig ab');
            $table->date('valid_until')->nullable()->comment('Gueltig bis');
            $table->timestamps();
        });

        // Reklamationen
        Schema::create('supplier_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('gas_station_id')->nullable()->constrained('gas_stations')->nullOnDelete()->comment('Betroffene Tankstelle');
            $table->string('subject')->comment('Betreff');
            $table->text('description')->comment('Beschreibung');
            $table->string('status', 20)->default('open')->comment('open, in_progress, resolved, closed');
            $table->string('priority', 10)->default('medium')->comment('low, medium, high');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('attachments')->nullable()->comment('Dateianhaenge');
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_complaints');
        Schema::dropIfExists('supplier_price_lists');
        Schema::dropIfExists('supplier_gas_station');
        Schema::dropIfExists('suppliers');
    }
};
