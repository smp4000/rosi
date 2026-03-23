<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rechnungspositionen aus ZUGFeRD-XML.
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary()->comment('UUID v7');
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete()->comment('Zugehoerige Rechnung');

            $table->string('article', 500)->nullable()->comment('Artikelname / Beschreibung');
            $table->decimal('quantity', 10, 3)->default(1)->comment('Menge');
            $table->decimal('unit_price', 10, 4)->default(0)->comment('Einzelpreis');
            $table->decimal('total_price', 10, 2)->default(0)->comment('Gesamtpreis der Position');
            $table->date('date')->nullable()->comment('Datum der Position');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
