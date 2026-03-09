<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schichtplanung-Tabellen erstellen.
     */
    public function up(): void
    {
        // Schichtvorlagen
        Schema::create('shift_templates', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('gas_station_id')->nullable()->constrained('gas_stations')->nullOnDelete();
            $table->string('name')->comment('z.B. Fruehschicht, Spaetschicht');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('break_minutes')->default(0);
            $table->string('shift_type', 20)->default('custom')->comment('early, late, night, split, custom');
            $table->string('color', 7)->nullable()->comment('Farbe fuer Kalender (#hex)');
            $table->timestamps();

            $table->index('tenant_id');
        });

        // Schichten
        Schema::create('shifts', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('gas_station_id')->constrained('gas_stations')->cascadeOnDelete()->comment('Tankstelle');
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete()->comment('Mitarbeiter');
            // --- Schicht ---
            $table->date('date')->comment('Datum');
            $table->time('start_time')->comment('Beginn');
            $table->time('end_time')->comment('Ende');
            $table->unsignedInteger('break_minutes')->default(0)->comment('Pause in Minuten');
            $table->string('shift_type', 20)->default('custom')->comment('early, late, night, split, custom');
            // --- Status ---
            $table->string('status', 20)->default('planned')->comment('planned, confirmed, swap_requested, swapped, cancelled, completed');
            $table->text('notes')->nullable();
            $table->foreignUuid('swap_requested_by')->nullable()->constrained('users')->nullOnDelete()->comment('Tausch angefragt von');
            $table->foreignUuid('swap_target_user_id')->nullable()->constrained('users')->nullOnDelete()->comment('Tausch-Ziel');
            $table->foreignUuid('confirmed_by')->nullable()->constrained('users')->nullOnDelete()->comment('Bestaetigt von');
            $table->time('actual_start')->nullable()->comment('Tatsaechlicher Beginn');
            $table->time('actual_end')->nullable()->comment('Tatsaechliches Ende');
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'date']);
            $table->index(['gas_station_id', 'date']);
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('shift_templates');
    }
};
