<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nachdruck-Protokoll fuer Gutschein-Etiketten (revisionssicher).
     * Pro nachgedrucktem Etikett ein Datensatz: wer, welche Nummer, wohin, wann.
     * "Wie oft" = Anzahl Datensaetze pro voucher_number.
     */
    public function up(): void
    {
        Schema::create('voucher_reprints', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('station_id')->nullable();
            $table->foreign('station_id')->references('id')->on('gas_stations')->nullOnDelete();

            $table->uuid('voucher_id')->nullable();
            $table->foreign('voucher_id')->references('id')->on('vouchers')->nullOnDelete();

            $table->string('voucher_number', 50);

            $table->uuid('user_id')->nullable()->comment('Wer hat nachgedruckt');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->uuid('print_job_id')->nullable()->comment('Zugehoeriger Druckauftrag');
            $table->uuid('target_agent_id')->nullable()->comment('Ziel-Drucker/Standort');

            $table->timestamps();

            $table->index(['tenant_id', 'voucher_number']);
            $table->index(['station_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_reprints');
    }
};
