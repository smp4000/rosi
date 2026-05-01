<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schichtabrechnungen: Tagesabrechnung pro Mitarbeiter und Schicht.
 * Erfasst Muenzrollen, Zaehlerstaende, Tresor-Einlagen, Warenruecknahmen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('gas_station_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete()->comment('Mitarbeiter');
            $table->foreignUuid('shift_id')->nullable()->constrained('shifts')->nullOnDelete()
                ->comment('Optionale Verknuepfung zur Schichtplanung');

            // Status-Workflow
            $table->enum('status', ['active', 'completed', 'cancelled'])
                ->default('active')
                ->comment('active=Schicht laeuft, completed=Abgeschlossen, cancelled=Abgebrochen');

            // Zeitstempel
            $table->dateTime('started_at')->comment('Schichtbeginn');
            $table->dateTime('ended_at')->nullable()->comment('Schichtende');

            // Kasse
            $table->decimal('cash_remaining', 10, 2)->default(0)->comment('Rest-Bargeld in der Kasse');
            $table->decimal('safe_total', 10, 2)->default(0)->comment('Gesamtbetrag Tresor-Einlagen');
            $table->decimal('cash_report_soll', 10, 2)->default(0)->comment('Soll-Betrag laut Kassenbericht');
            $table->decimal('cash_difference', 10, 2)->default(0)->comment('Differenz Soll - Ist');

            // Belege
            $table->string('cash_report_photo')->nullable()->comment('Pfad zum Kassenbericht-Foto');
            $table->text('notes')->nullable()->comment('Anmerkungen');
            $table->longText('signature')->nullable()->comment('Digitale Unterschrift (Base64 PNG)');

            $table->timestamps();
            $table->softDeletes();

            // Indizes
            $table->index(['tenant_id', 'status']);
            $table->index(['gas_station_id', 'started_at']);
            $table->index(['user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_settlements');
    }
};
