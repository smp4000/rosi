<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Abschriften (Warenverluste): erfasste Abschreibungen aus der POS-App.
     * Quelle fuer die Abschriften-/Tagesberichte.
     *
     * Drei Erfassungswege (source):
     * - 'batch'  : mehrere Artikel gescannt, ein gemeinsamer Grund
     * - 'single' : Einzel-Abschreibung
     * - 'mhd'    : aus dem MHD-Abschreiben (Grund: MHD-Ueberschreitung)
     *
     * EK/VK werden als Snapshot gespeichert (Preis zum Erfassungszeitpunkt
     * aus dem Artikel) — fuer korrekte Bericht-Summen auch bei Preisaenderung.
     */
    public function up(): void
    {
        Schema::create('depreciation_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('tenant_id')->comment('Mandant');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('station_id')->comment('Tankstelle');
            $table->foreign('station_id')->references('id')->on('gas_stations')->cascadeOnDelete();

            $table->uuid('user_id')->nullable()->comment('Wer hat erfasst');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            // Artikel-Snapshot (Artikel kann unbekannt sein -> manuell erfasst)
            $table->string('ean')->nullable();
            $table->unsignedBigInteger('tms_no')->nullable();
            $table->string('article_description');
            $table->uuid('article_id')->nullable()->comment('Verknuepfter Artikel, falls bekannt');

            $table->integer('quantity')->default(1);

            // Grund (depreciation_reasons.id = BIGINT)
            $table->unsignedBigInteger('depreciation_reason_id')->nullable();
            $table->foreign('depreciation_reason_id')->references('id')->on('depreciation_reasons')->nullOnDelete();

            // Preis-Snapshot
            $table->decimal('purchasing_price', 10, 3)->nullable()->comment('EK zum Erfassungszeitpunkt');
            $table->decimal('selling_price', 10, 2)->nullable()->comment('VK zum Erfassungszeitpunkt');

            $table->string('source', 10)->default('single')->comment('batch | single | mhd');

            // Herkunft aus MHD (mhds.id = BIGINT)
            $table->unsignedBigInteger('mhd_id')->nullable();

            $table->string('note')->nullable();
            $table->timestamp('recorded_at')->comment('Erfassungszeitpunkt');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'station_id', 'recorded_at']);
            $table->index('depreciation_reason_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_entries');
    }
};
