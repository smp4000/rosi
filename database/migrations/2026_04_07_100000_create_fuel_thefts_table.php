<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kraftstoffdiebstaehle: Meldungen von Tankbetruegen.
 * Wizard-Formular mit 5 Steps: Vorfall → Fahrzeug → Umstaende → Belege → Abschluss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_thefts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('gas_station_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('reported_by')->constrained('users')->cascadeOnDelete();

            // Status-Workflow
            $table->enum('status', ['draft', 'submitted', 'in_progress', 'resolved', 'rejected'])
                ->default('draft')
                ->comment('draft=Entwurf, submitted=Gesendet, in_progress=In Bearbeitung, resolved=Erledigt, rejected=Abgelehnt');

            // Step 1: Vorfall
            $table->dateTime('incident_at')->comment('Zeitpunkt des Vorfalls');
            $table->string('product')->comment('Kraftstoffart (Super E5, Diesel, etc.)');
            $table->unsignedSmallInteger('pump_number')->comment('Zapfpunkt-Nummer');
            $table->decimal('quantity', 10, 3)->comment('Menge in Liter oder kg');
            $table->decimal('amount', 10, 2)->comment('Betrag in EUR');

            // Step 2: Fahrzeug
            $table->boolean('license_plate_available')->default(true)->comment('KFZ-Kennzeichen verfuegbar?');
            $table->string('license_plate', 20)->nullable()->comment('KFZ-Kennzeichen (Format: ABC-AB 1234)');
            $table->boolean('is_special_plate')->default(false)->comment('Spezielles/auslaendisches Kennzeichen');
            $table->boolean('has_video_evidence')->default(false)->comment('Videobeweis liegt vor');
            // Gruende wenn kein Kennzeichen
            $table->boolean('plate_not_readable')->default(false)->comment('Kennzeichen nicht eindeutig lesbar');
            $table->boolean('plate_stolen')->default(false)->comment('Bekanntermaßen gestohlenes Kennzeichen');
            $table->boolean('camera_defective')->default(false)->comment('Videoanlage defekt');
            $table->boolean('camera_missing')->default(false)->comment('Videoanlage nicht vorhanden');
            $table->string('no_plate_other_reason')->nullable()->comment('Sonstiger Grund fuer fehlendes Kennzeichen');

            // Step 3: Umstaende
            $table->boolean('pump_confusion')->default(false)->comment('Saeulenverwechselung');
            $table->boolean('card_payment_error')->default(false)->comment('Fehler Kartenzahlung');
            $table->enum('shop_purchase', ['card', 'cash', 'none'])->default('none')
                ->comment('Shopkauf: card=Kartenzahlung, cash=Barzahlung, none=Kein Shopkauf');
            $table->boolean('transfer_inkasso')->default(false)->comment('Transfer Inkasso');
            // Zeuge/Kassierer
            $table->string('witness_first_name')->nullable();
            $table->string('witness_last_name')->nullable();
            $table->string('witness_address')->nullable();
            $table->string('witness_zip', 10)->nullable();
            $table->string('witness_city')->nullable();
            $table->string('witness_country', 5)->nullable();
            $table->text('comment')->nullable()->comment('Kommentar / Bemerkungen');

            // Step 4: Belege & Unterschrift
            $table->string('receipt_path')->nullable()->comment('Pfad zum Tankbeleg (Foto/PDF)');
            $table->json('additional_documents')->nullable()->comment('Weitere Belege (Array von Pfaden)');
            $table->text('signature')->nullable()->comment('Digitale Unterschrift (Base64 PNG)');

            // Step 5: Rechtsbelehrung
            $table->boolean('legal_notice_accepted')->default(false);
            $table->dateTime('legal_notice_accepted_at')->nullable();

            // Verwaltung
            $table->string('police_reference')->nullable()->comment('Tagebuchnummer der Polizei');
            $table->text('internal_notes')->nullable()->comment('Interne Notizen (nur Partner/Admin)');
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('resolved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indizes
            $table->index(['tenant_id', 'status']);
            $table->index(['gas_station_id', 'incident_at']);
            $table->index('license_plate');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_thefts');
    }
};
