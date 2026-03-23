<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Versandfelder fuer Firmenkundenrechnungen.
     */
    public function up(): void
    {
        Schema::table('corporate_customers', function (Blueprint $table) {
            $table->boolean('send_via_email')->default(true)
                ->after('notes')
                ->comment('Rechnung per E-Mail versenden');
            $table->boolean('send_via_print')->default(false)
                ->after('send_via_email')
                ->comment('Rechnung drucken (Sammel-PDF)');
            $table->boolean('is_active')->default(true)
                ->after('send_via_print')
                ->comment('Kunde aktiv (inaktive werden uebersprungen)');
        });
    }

    public function down(): void
    {
        Schema::table('corporate_customers', function (Blueprint $table) {
            $table->dropColumn(['send_via_email', 'send_via_print', 'is_active']);
        });
    }
};
