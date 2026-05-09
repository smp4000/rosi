<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zeitschriften-Kiosk: Artikel-Stammdaten (Presseerzeugnisse).
 *
 * Eindeutig pro Tenant + Lieferant + Objektnummer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('supplier', 50)->default('PVG');
            $table->string('objekt', 20);
            $table->string('ean', 20)->nullable();
            $table->tinyInteger('weekday')->nullable()->comment('1=Mo..7=So fuer Tageszeitungen');
            $table->string('bezeichnung');
            $table->decimal('aktueller_preis_netto', 10, 4)->default(0);
            $table->decimal('aktueller_preis_brutto', 10, 4)->default(0);
            $table->decimal('mwst_satz', 5, 2)->default(0);
            $table->decimal('ek', 10, 4)->nullable();
            $table->boolean('is_pending')->default(false)->comment('1=von App angelegt, noch keine Rechnung');
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'supplier', 'objekt'], 'uq_kiosk_article');
            $table->index(['tenant_id', 'ean']);
            $table->index(['tenant_id', 'ean', 'weekday']);
            $table->index(['tenant_id', 'is_pending']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_articles');
    }
};
