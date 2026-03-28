<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registrierte Geraete fuer die POS-App.
     * Jedes Geraet (Zebra MDE oder Mitarbeiter-Handy) wird hier gespeichert.
     */
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            // Primaerschluessel (UUID wie ueberall in Rosi)
            $table->uuid('id')->primary();

            // Zu welchem Mandanten gehoert das Geraet?
            $table->uuid('tenant_id')->comment('Mandant');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            // An welcher Tankstelle ist das Geraet?
            $table->uuid('station_id')->comment('Tankstelle');
            $table->foreign('station_id')->references('id')->on('gas_stations')->cascadeOnDelete();

            // Welcher Mitarbeiter hat das Geraet? (nur bei persoenlichen Handys)
            $table->uuid('user_id')->nullable()->comment('Mitarbeiter (nur bei persoenlichem Handy)');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            // Was fuer ein Geraet ist es?
            $table->enum('device_type', ['mde', 'personal'])->comment('mde = Firmengeraet, personal = eigenes Handy');

            // Geraete-Infos (wird beim Registrieren automatisch ermittelt)
            $table->string('device_name')->nullable()->comment('z.B. Zebra TC21, Samsung Galaxy S24');
            $table->string('device_os')->nullable()->comment('z.B. Android 14, iOS 17');
            $table->string('app_version')->nullable()->comment('Installierte App-Version');

            // Sicherheit
            $table->string('device_token_hash')->unique()->comment('Hash des Device-Tokens (wie Passwort)');
            $table->boolean('is_active')->default(true)->comment('Admin kann Geraet deaktivieren');

            // Wann zuletzt benutzt?
            $table->timestamp('last_seen_at')->nullable()->comment('Letzter Kontakt mit der API');
            $table->timestamps();
            $table->softDeletes();

            // Indexe fuer schnelle Abfragen
            $table->index(['tenant_id', 'station_id']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
