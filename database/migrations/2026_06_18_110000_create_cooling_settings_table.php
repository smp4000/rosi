<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Einstellungen fuer das Temperatur-Management (Mobile Alerts API).
 * phoneid pro Mandant — optional pro Station ueberschreibbar (station_id null = Tenant-Default).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cooling_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('station_id')->nullable(); // null = Standard fuer den ganzen Mandanten
            $table->string('phoneid')->nullable();  // Mobile-Alerts Phone-ID (fuer Alert-Flags)
            $table->string('base_url')->nullable();  // Standard: https://www.data199.com/api/pv1
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('poll_interval_min')->default(5);
            $table->timestamps();

            $table->unique(['tenant_id', 'station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooling_settings');
    }
};
