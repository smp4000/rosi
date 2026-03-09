<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tankstellen-Tabelle erstellen.
     */
    public function up(): void
    {
        Schema::create('gas_stations', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name')->comment('Tankstellenname');
            $table->string('brand', 100)->nullable()->comment('Marke (Aral, Shell, frei, etc.)');
            $table->string('station_number', 50)->nullable()->comment('Interne Stationsnummer');
            // Adresse
            $table->string('street')->nullable()->comment('Strasse');
            $table->string('zip', 10)->nullable()->comment('PLZ');
            $table->string('city')->nullable()->comment('Ort');
            $table->string('state', 100)->nullable()->comment('Bundesland');
            $table->string('country', 2)->default('DE');
            // Geo-Koordinaten
            $table->decimal('latitude', 10, 8)->nullable()->comment('Breitengrad');
            $table->decimal('longitude', 11, 8)->nullable()->comment('Laengengrad');
            // Kontakt
            $table->string('phone', 50)->nullable();
            $table->string('fax', 50)->nullable();
            $table->string('email')->nullable();
            // Geschaeftsdaten
            $table->string('tax_id', 50)->nullable()->comment('Steuernummer der Tankstelle');
            $table->string('trade_register', 100)->nullable()->comment('Handelsregisternummer');
            // Ausstattung
            $table->unsignedInteger('num_pumps')->nullable()->comment('Anzahl Zapfsaeulen');
            $table->boolean('has_shop')->default(false)->comment('Hat Shop');
            $table->boolean('has_car_wash')->default(false)->comment('Hat Waschanlage');
            $table->json('opening_hours')->nullable()->comment('Oeffnungszeiten (Mo-So)');
            $table->json('services')->nullable()->comment('Verfuegbare Services (LPG, AdBlue, etc.)');
            // Medien
            $table->string('logo')->nullable()->comment('Tankstellen-Logo/Foto');
            $table->json('photos')->nullable()->comment('Weitere Fotos (Array von Pfaden)');
            // Sonstiges
            $table->text('notes')->nullable()->comment('Interne Notizen');
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable()->comment('Stations-spezifische Einstellungen');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
        });

        // Pivot: Mitarbeiter <-> Tankstelle
        Schema::create('gas_station_user', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('gas_station_id')->constrained('gas_stations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('station_role', 50)->nullable()->comment('Zusaetzliche Rolle an dieser Station');
            $table->boolean('is_primary')->default(false)->comment('Haupt-Tankstelle des Mitarbeiters');
            $table->timestamp('assigned_at')->nullable()->comment('Zuweisungsdatum');
            $table->timestamps();

            $table->unique(['gas_station_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gas_station_user');
        Schema::dropIfExists('gas_stations');
    }
};
