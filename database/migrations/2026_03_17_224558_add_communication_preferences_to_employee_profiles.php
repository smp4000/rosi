<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kommunikations-Praeferenzen fuer Mitarbeiter.
 * Bevorzugter Kanal + DSGVO-Consent fuer Messenger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->string('preferred_channel', 20)->default('email')->after('status'); // email, telegram, whatsapp
            $table->timestamp('communication_consent_at')->nullable()->after('preferred_channel');
            $table->string('communication_consent_ip', 45)->nullable()->after('communication_consent_at');
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn(['preferred_channel', 'communication_consent_at', 'communication_consent_ip']);
        });
    }
};
