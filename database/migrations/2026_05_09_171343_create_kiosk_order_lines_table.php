<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('kiosk_invoices')->cascadeOnDelete();
            $table->foreignUuid('article_id')->nullable()->constrained('kiosk_articles')->nullOnDelete();
            $table->enum('typ', ['lieferung', 'remission']);
            $table->string('lieferschein_nr', 50)->nullable();
            $table->date('lieferschein_datum')->nullable();
            $table->string('paket', 50)->nullable();
            $table->string('ausgabe', 10)->nullable();
            $table->integer('menge')->comment('positiv=Lieferung, negativ=Remission');
            $table->decimal('einzelpreis_netto', 10, 4)->default(0);
            $table->decimal('einzelpreis_brutto', 10, 4)->default(0);
            $table->decimal('mwst_satz', 5, 2)->default(0);
            $table->decimal('gesamt_netto', 12, 4)->default(0);
            $table->decimal('gesamt_brutto', 12, 4)->default(0);
            $table->timestamps();

            $table->index(['invoice_id', 'typ']);
            $table->index('article_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_order_lines');
    }
};
