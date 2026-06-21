<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kuehlmoebel (Kuehlschrank/Truhe/Theke/Kuehlraum) mit Mobile-Alerts-Sensor-Zuordnung
 * und Soll-Temperaturbereich. Grundlage fuer Temperatur- und Stoerungs-Management.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooling_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('station_id')->index();

            $table->string('name');                       // z.B. "Fisch-Theke"
            $table->string('type')->default('fridge');    // fridge|freezer|counter|cold_room|other
            $table->string('category')->nullable();       // z.B. "Fisch", "Pasta"

            // ── Mobile-Alerts-Sensor ──
            $table->string('device_id')->index();         // Sensor-ID (pro Station unterschiedlich)
            $table->string('sensor_type')->default('ID02'); // ID01..ID20
            $table->string('channel')->default('t1');     // t1 (Luft) | t2 (Fuehler im Moebel)
            $table->string('phoneid')->nullable();        // optionaler Sensor-Override

            // ── Soll-Bereich (ROSI-eigene Grenzwerte) ──
            $table->decimal('target_min', 5, 2)->nullable();
            $table->decimal('target_max', 5, 2)->nullable();
            $table->boolean('track_humidity')->default(false);
            $table->decimal('humidity_min', 5, 2)->nullable();
            $table->decimal('humidity_max', 5, 2)->nullable();

            $table->unsignedSmallInteger('offline_after_min')->default(30); // ohne Messung => offline

            // ── Letzter Stand (Cache fuer schnelle Anzeige) ──
            $table->timestamp('last_reading_at')->nullable();
            $table->decimal('last_value', 6, 2)->nullable();
            $table->decimal('last_humidity', 5, 2)->nullable();
            $table->boolean('last_lowbat')->default(false);
            $table->string('last_status')->nullable();    // ok|high|low|offline

            $table->boolean('is_active')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooling_units');
    }
};
