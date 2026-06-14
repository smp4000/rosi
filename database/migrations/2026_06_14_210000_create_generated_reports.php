<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Berichts-Archiv: jede erzeugte PDF-Datei (Abschriften-Tagesbericht,
     * MHD-Bericht, ...) wird hier protokolliert und bleibt abrufbar.
     *
     * type: 'depreciation' | 'mhd' | ...
     * meta: Kennzahlen (Anzahl, Summen EK/VK) als JSON fuer die Listendarstellung.
     */
    public function up(): void
    {
        Schema::create('generated_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('tenant_id')->comment('Mandant');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->uuid('station_id')->nullable()->comment('Tankstelle (null = alle Stationen)');
            $table->foreign('station_id')->references('id')->on('gas_stations')->nullOnDelete();

            $table->uuid('user_id')->nullable()->comment('Wer hat erzeugt');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();

            $table->string('type', 30)->default('depreciation')->comment('depreciation | mhd | ...');
            $table->string('title');
            $table->string('file_path')->comment('Pfad auf disk local');
            $table->unsignedBigInteger('file_size')->nullable();

            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();

            $table->json('meta')->nullable()->comment('Kennzahlen: count, total_ek, total_vk, ...');

            $table->timestamps();

            $table->index(['tenant_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
    }
};
