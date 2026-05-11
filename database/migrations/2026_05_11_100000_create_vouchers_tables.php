<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gutschein-Modul: Tabellen fuer Gutscheine und Einloesungen.
 *
 * vouchers: Jede Zeile = ein Gutschein (z.B. 4567.000)
 * voucher_redemptions: Log der (Teil-)Einloesungen
 */
return new class extends Migration
{
    public function up(): void
    {
        // -- Gutscheine --
        Schema::create('vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('station_id')->comment('Tankstelle an der der Gutschein ausgegeben wurde');

            // Nummern-System: voucher_group = "4567", voucher_number = "4567.000"
            $table->string('voucher_group', 20)->comment('Basisnummer aus dem WaWi, z.B. 4567');
            $table->string('voucher_number', 30)->unique()->comment('Eindeutige Nummer, z.B. 4567.000');

            // Betraege
            $table->decimal('amount', 8, 2)->comment('Urspruenglicher Gutscheinwert');
            $table->decimal('remaining_amount', 8, 2)->comment('Aktuelles Restguthaben');

            // Datum
            $table->date('issued_at')->comment('Ausgabedatum');
            $table->date('valid_until')->comment('Gueltig bis (issued_at + 3 Jahre)');

            // Status: active, partially_redeemed, redeemed, expired, cancelled
            $table->string('status', 30)->default('active');

            // Mitarbeiter der den Gutschein ausgegeben hat
            $table->uuid('employee_id')->nullable();
            $table->string('employee_name')->nullable()->comment('Name zum Zeitpunkt der Ausgabe');

            $table->timestamps();
            $table->softDeletes();

            // Indizes
            $table->foreign('tenant_id')->references('id')->on('gas_stations')->cascadeOnDelete();
            $table->foreign('station_id')->references('id')->on('gas_stations')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('users')->nullOnDelete();

            $table->index('tenant_id');
            $table->index('station_id');
            $table->index('voucher_group');
            $table->index('status');
            $table->index('valid_until');
        });

        // -- Einloesungen --
        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('voucher_id');
            $table->uuid('station_id')->comment('Tankstelle an der eingeloest wurde');

            $table->decimal('amount', 8, 2)->comment('Eingeloester Betrag');
            $table->decimal('remaining_after', 8, 2)->comment('Restguthaben nach dieser Einloesung');

            // Mitarbeiter der eingeloest hat
            $table->uuid('employee_id')->nullable();
            $table->string('employee_name')->nullable();

            $table->timestamp('redeemed_at');
            $table->text('notes')->nullable();

            $table->timestamps();

            // FKs
            $table->foreign('voucher_id')->references('id')->on('vouchers')->cascadeOnDelete();
            $table->foreign('station_id')->references('id')->on('gas_stations')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('users')->nullOnDelete();

            $table->index('voucher_id');
            $table->index('station_id');
            $table->index('redeemed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('vouchers');
    }
};
