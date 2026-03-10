<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Erweitert gas_stations um Allgemein-, Adress-, Shop- und Oeffnungszeiten-Felder.
 * Erstellt gas_station_bank_accounts fuer Bankkonten-Verwaltung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            // --- Tab "Allgemein" ---
            $table->string('sales_channel', 100)->nullable()->after('station_number')
                ->comment('Vertriebskanal');
            $table->string('ownership_type', 10)->nullable()->after('sales_channel')
                ->comment('Eigentumsverhaeltnis (DOFO, COFO, DODO, CODO, COCO)');
            $table->string('district', 100)->nullable()->after('ownership_type')
                ->comment('Distrikt');
            $table->string('district_description')->nullable()->after('district')
                ->comment('Distrikt-Beschreibung');
            $table->string('region', 100)->nullable()->after('district_description')
                ->comment('Bezirk');
            $table->string('region_manager')->nullable()->after('region')
                ->comment('Bezirksleitung');
            $table->string('station_number_fuel', 50)->nullable()->after('region_manager')
                ->comment('Tst.-Nr. Kraftstoff');
            $table->string('station_number_shop', 50)->nullable()->after('station_number_fuel')
                ->comment('Tst.-Nr. Shop');
            $table->boolean('has_toll_terminal')->default(false)->after('station_number_shop')
                ->comment('Mautstellenterminal vorhanden');

            // --- Tab "Adresse" (erweitert) ---
            $table->string('academic_title', 20)->nullable()->after('country')
                ->comment('Akad. Grad');
            $table->string('contact_first_name')->nullable()->after('academic_title')
                ->comment('Ansprechpartner Vorname');
            $table->string('contact_last_name')->nullable()->after('contact_first_name')
                ->comment('Ansprechpartner Nachname');
            $table->string('house_number', 20)->nullable()->after('street')
                ->comment('Hausnummer');
            $table->string('district_part', 100)->nullable()->after('city')
                ->comment('Ortsteil');
            $table->string('website')->nullable()->after('email')
                ->comment('Webseite');

            // --- Tab "Oeffnungszeiten" (erweitert) ---
            $table->date('first_opening_ok')->nullable()->after('opening_hours')
                ->comment('Erstoeffnung OK');
            $table->date('first_opening_dk')->nullable()->after('first_opening_ok')
                ->comment('Erstoeffnung DK');

            // --- Tab "Shop" ---
            $table->string('shop_size', 50)->nullable()->after('has_car_wash')
                ->comment('Shopgroesse');
            $table->string('shop_type', 50)->nullable()->after('shop_size')
                ->comment('Shoptyp');
            $table->string('shop_class', 50)->nullable()->after('shop_type')
                ->comment('Shop Klasse');
            $table->date('shop_setup_date')->nullable()->after('shop_class')
                ->comment('Einrichtungsdatum Shop');
            $table->string('nielsen_area', 10)->nullable()->after('shop_setup_date')
                ->comment('Nielsen-Gebiet');
            $table->string('price_region', 50)->nullable()->after('nielsen_area')
                ->comment('Preisregion');
            $table->string('assortment_level', 50)->nullable()->after('price_region')
                ->comment('Sortimentsstufe');
            $table->string('shop_partner')->nullable()->after('assortment_level')
                ->comment('Shop Partner');
            $table->string('shop_operation_number', 50)->nullable()->after('shop_partner')
                ->comment('Shop-Betriebsnummer');
        });

        // --- Bankkonten-Tabelle ---
        Schema::create('gas_station_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('gas_station_id')
                ->constrained('gas_stations')
                ->cascadeOnDelete();
            $table->string('iban', 34)->comment('IBAN');
            $table->string('bank_name')->nullable()->comment('Bankname');
            $table->string('bic', 11)->nullable()->comment('BIC/SWIFT');
            $table->string('description')->nullable()->comment('Beschreibung');
            $table->string('account_type', 50)->nullable()
                ->comment('Kontotyp (Geschaeftskonto, Agenturkonto, Lottokonto)');
            $table->timestamps();

            $table->index('gas_station_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gas_station_bank_accounts');

        Schema::table('gas_stations', function (Blueprint $table) {
            $table->dropColumn([
                'sales_channel', 'ownership_type', 'district', 'district_description',
                'region', 'region_manager', 'station_number_fuel', 'station_number_shop',
                'has_toll_terminal', 'academic_title', 'contact_first_name', 'contact_last_name',
                'house_number', 'district_part', 'website', 'first_opening_ok', 'first_opening_dk',
                'shop_size', 'shop_type', 'shop_class', 'shop_setup_date', 'nielsen_area',
                'price_region', 'assortment_level', 'shop_partner', 'shop_operation_number',
            ]);
        });
    }
};
