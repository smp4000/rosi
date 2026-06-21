<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HACCP-Erweiterung (ARAL HQM 7.0): Maßnahmenkatalog-Tabelle, Geraetenummer (#)
 * fuer das Formblatt, und Maßnahmenfeld (Maßnahme(n) + Kontrollmessung + Ticketnr.)
 * an der Stoerung.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Maßnahmenkatalog (9 feste Ziffern, systemweit; pro Mandant erweiterbar) ──
        Schema::create('cooling_measures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();   // null = System-Eintrag (ARAL-Katalog)
            $table->unsignedSmallInteger('number');  // Ziffer 1..9
            $table->string('text', 500);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'number']);
        });

        // ── Geraetenummer (#) fuer das Formblatt ──
        Schema::table('cooling_units', function (Blueprint $table) {
            $table->unsignedSmallInteger('device_number')->nullable()->after('name');
            $table->string('preset')->nullable()->after('category'); // gewaehltes Grenzwert-Preset
        });

        // ── Maßnahmenfeld an der Stoerung (Dokumentation der getroffenen Maßnahme) ──
        Schema::table('cooling_alerts', function (Blueprint $table) {
            $table->json('measures')->nullable()->after('note');       // gewaehlte Ziffern [1,4]
            $table->decimal('control_value', 6, 2)->nullable()->after('measures'); // Kontrollmessung
            $table->string('ticket_number')->nullable()->after('control_value');   // bei Ziffer 5
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooling_measures');
        Schema::table('cooling_units', function (Blueprint $table) {
            $table->dropColumn(['device_number', 'preset']);
        });
        Schema::table('cooling_alerts', function (Blueprint $table) {
            $table->dropColumn(['measures', 'control_value', 'ticket_number']);
        });
    }
};
