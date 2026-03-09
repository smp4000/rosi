<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migrationen ausfuehren.
     * Erstellt: tenants, users, password_reset_tokens, sessions
     */
    public function up(): void
    {
        // Tenants-Tabelle muss zuerst erstellt werden (FK-Abhaengigkeit)
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->string('name')->comment('Firmenname des Partners');
            $table->string('slug')->unique()->comment('URL-freundlicher Name');
            // owner_id wird spaeter per ALTER hinzugefuegt (zirkulaere FK)
            $table->string('email')->comment('Firmen-E-Mail');
            $table->string('phone', 50)->nullable()->comment('Telefon');
            $table->string('street')->nullable()->comment('Strasse');
            $table->string('zip', 10)->nullable()->comment('PLZ');
            $table->string('city')->nullable()->comment('Ort');
            $table->string('country', 2)->default('DE')->comment('Laendercode ISO');
            $table->string('logo')->nullable()->comment('Pfad zum Logo');
            $table->string('tax_id', 50)->nullable()->comment('Steuernummer');
            $table->string('trade_register', 100)->nullable()->comment('Handelsregisternummer');
            $table->dateTime('trial_ends_at')->nullable()->comment('Ende der 14-Tage-Testphase');
            $table->string('subscription_status', 20)->default('trial')->comment('trial, active, expired, cancelled');
            $table->string('subscription_plan', 50)->nullable()->comment('Gewaehltes Preismodell');
            $table->json('settings')->nullable()->comment('Mandanten-spezifische Einstellungen');
            $table->boolean('is_active')->default(true)->comment('Aktiv/Gesperrt');
            $table->timestamps();
            $table->softDeletes();
        });

        // Users-Tabelle mit UUID und erweiterten Feldern
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants')->nullOnDelete()->comment('NULL = Super-Admin');
            $table->string('first_name')->nullable()->comment('Vorname (optional, z.B. bei Firmen leer)');
            $table->string('last_name')->comment('Nachname / Firmenname (Pflichtfeld)');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable()->comment('E-Mail bestaetigt');
            $table->string('type', 20)->default('employee')->comment('super_admin, partner, employee, customer');
            $table->string('avatar')->nullable()->comment('Profilbild-Pfad');
            $table->string('phone', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('locale', 5)->default('de')->comment('Sprache');
            $table->timestamp('last_login_at')->nullable()->comment('Letzter Login');
            $table->text('two_factor_secret')->nullable()->comment('2FA Secret (verschluesselt)');
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('type');
        });

        // Zirkulaere FK: tenants.owner_id -> users.id
        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignUuid('owner_id')->nullable()->after('slug')->constrained('users')->nullOnDelete()->comment('Inhaber/Ersteller');
        });

        // Standard Laravel Tabellen
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->char('user_id', 36)->nullable()->index()->comment('UUID des Users');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Migrationen rueckgaengig machen.
     */
    public function down(): void
    {
        // FK zuerst entfernen wegen zirkulaerer Abhaengigkeit
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('tenants');
    }
};
