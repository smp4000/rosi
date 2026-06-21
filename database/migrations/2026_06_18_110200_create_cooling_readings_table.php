<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temperatur-Historie (selbst gespeichert, da die Mobile-Alerts-API nur die
 * letzte Messung liefert). Grundlage fuer Verlaufsdiagramm + HACCP-Bericht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooling_readings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('cooling_unit_id')->index();
            $table->uuid('tenant_id');
            $table->uuid('station_id');

            $table->decimal('value', 6, 2)->nullable();      // Temperatur °C
            $table->decimal('humidity', 5, 2)->nullable();   // Feuchte %
            $table->timestamp('measured_at');                // ts vom Sensor
            $table->timestamp('received_at')->nullable();    // c vom Server
            $table->unsignedBigInteger('mobile_idx')->nullable(); // idx der Messung (Dedup)
            $table->boolean('out_of_range')->default(false); // 65295
            $table->boolean('disconnected')->default(false); // 43530
            $table->timestamps();

            $table->index(['cooling_unit_id', 'measured_at']);
            $table->unique(['cooling_unit_id', 'mobile_idx']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooling_readings');
    }
};
