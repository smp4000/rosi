<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('supplier', 50)->default('PVG');
            $table->string('rechnungsnummer', 50);
            $table->date('rechnungsdatum')->nullable();
            $table->date('lieferdatum_von')->nullable();
            $table->date('lieferdatum_bis')->nullable();
            $table->string('kundennummer', 50)->nullable();
            $table->decimal('gesamtbetrag', 12, 4)->nullable();
            $table->string('filename')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'supplier', 'rechnungsnummer'], 'uq_kiosk_invoice');
            $table->index(['tenant_id', 'file_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_invoices');
    }
};
