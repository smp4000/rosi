<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workflow-Felder fuer Tankbetrug-Meldungen:
 * - Anzeige (Polizei) mit 4-Tage-Sperre
 * - Einreichung bei Gesellschaft (Aral)
 * - Zahlungsstatus und -betrag
 * - Medien-Uploads (nachtraeglich, nicht im Wizard)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_thefts', function (Blueprint $table) {
            // Anzeige bei Polizei
            $table->dateTime('police_report_at')->nullable()
                ->after('resolved_at')
                ->comment('Zeitpunkt der Anzeige-Generierung (erst 4 Tage nach Vorfall moeglich)');

            // Einreichung bei Gesellschaft (Aral)
            $table->dateTime('submitted_to_company_at')->nullable()
                ->after('police_report_at')
                ->comment('Zeitpunkt der Einreichung bei Aral/Gesellschaft');

            // Zahlungsstatus
            $table->enum('payment_status', ['open', 'partial', 'paid', 'written_off'])
                ->default('open')
                ->after('submitted_to_company_at')
                ->comment('open=Offen, partial=Teilzahlung, paid=Bezahlt, written_off=Abgeschrieben');

            $table->decimal('payment_amount', 10, 2)->default(0)
                ->after('payment_status')
                ->comment('Erhaltener Betrag in EUR');

            $table->dateTime('payment_received_at')->nullable()
                ->after('payment_amount')
                ->comment('Zeitpunkt der (letzten) Zahlung');

            // Medien-Uploads (nachtraeglich, nicht im Wizard)
            $table->json('media')->nullable()
                ->after('additional_documents')
                ->comment('Nachtraegliche Fotos/Videos (Array von Pfaden)');
        });
    }

    public function down(): void
    {
        Schema::table('fuel_thefts', function (Blueprint $table) {
            $table->dropColumn([
                'police_report_at',
                'submitted_to_company_at',
                'payment_status',
                'payment_amount',
                'payment_received_at',
                'media',
            ]);
        });
    }
};
