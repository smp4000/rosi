<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signatur-Token fuer oeffentliche Unterschrift-Seite.
 * Mitarbeiter erhaelt per E-Mail einen Link mit Token zum Unterschreiben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('signature_token', 64)->nullable()->unique()->after('signed_by_ip');
            $table->timestamp('signature_token_expires_at')->nullable()->after('signature_token');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['signature_token', 'signature_token_expires_at']);
        });
    }
};
