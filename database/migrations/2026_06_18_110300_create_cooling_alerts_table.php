<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stoerungen/Alarme je Kuehlmoebel (zu warm/kalt, offline, Batterie schwach).
 * Werden vom Poll-Job geoeffnet/geschlossen; quittierbar (Audit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooling_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cooling_unit_id')->index();
            $table->uuid('tenant_id');
            $table->uuid('station_id');

            $table->string('type');                       // high|low|offline|lowbat|hum_high|hum_low
            $table->decimal('value', 6, 2)->nullable();   // Messwert beim Ausloesen
            $table->decimal('threshold', 6, 2)->nullable(); // verletzte Schwelle
            $table->string('status')->default('active');  // active|resolved

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();

            $table->timestamp('acknowledged_at')->nullable();
            $table->uuid('acknowledged_by')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('notified_at')->nullable();

            $table->timestamps();

            $table->index(['cooling_unit_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooling_alerts');
    }
};
