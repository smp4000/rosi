<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Einladungen fuer die POS-App.
     * Partner laedt Mitarbeiter ein → MA bekommt Link → oeffnet App → Geraet wird registriert.
     */
    public function up(): void
    {
        Schema::create('device_invitations', function (Blueprint $table) {
            // Primaerschluessel
            $table->uuid('id')->primary();

            // Zu welchem Mandanten?
            $table->uuid('tenant_id')->comment('Mandant');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            // Fuer welche Tankstelle?
            $table->uuid('station_id')->comment('Tankstelle');
            $table->foreign('station_id')->references('id')->on('gas_stations')->cascadeOnDelete();

            // Fuer welchen Mitarbeiter?
            $table->uuid('user_id')->comment('Eingeladener Mitarbeiter');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Wer hat eingeladen?
            $table->uuid('invited_by')->nullable()->comment('Wer hat die Einladung erstellt?');
            $table->foreign('invited_by')->references('id')->on('users')->nullOnDelete();

            // Der geheime Token im Link (z.B. https://rosi.app/pos-setup/abc123xyz)
            $table->string('token', 64)->unique()->comment('Einmal-Token fuer den Einladungslink');

            // Wie wurde eingeladen?
            $table->enum('channel', ['email', 'sms', 'whatsapp', 'qr_code'])->default('email')->comment('Versandkanal');

            // Status der Einladung
            $table->enum('status', ['pending', 'accepted', 'expired', 'revoked'])->default('pending');

            // Wann laeuft die Einladung ab?
            $table->timestamp('expires_at')->comment('Gueltig bis (z.B. 72 Stunden)');

            // Wann wurde sie angenommen?
            $table->timestamp('accepted_at')->nullable();

            // Welches Geraet wurde registriert? (wird nach Annahme gesetzt)
            $table->uuid('device_id')->nullable()->comment('Verknuepftes Geraet nach Registrierung');
            $table->foreign('device_id')->references('id')->on('devices')->nullOnDelete();

            $table->timestamps();

            // Index: Schnell nach Token suchen
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_invitations');
    }
};
