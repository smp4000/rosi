<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_price_change_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('article_id')->constrained('kiosk_articles')->cascadeOnDelete();
            $table->string('change_type', 20)->comment('vkp|ek|mwst|multi');
            $table->decimal('old_preis_netto', 10, 4)->nullable();
            $table->decimal('new_preis_netto', 10, 4)->nullable();
            $table->decimal('old_preis_brutto', 10, 4)->nullable();
            $table->decimal('new_preis_brutto', 10, 4)->nullable();
            $table->decimal('old_mwst', 5, 2)->nullable();
            $table->decimal('new_mwst', 5, 2)->nullable();
            $table->decimal('old_ek', 10, 4)->nullable();
            $table->decimal('new_ek', 10, 4)->nullable();
            $table->string('source', 50)->nullable()->comment('ean_update|invoice_import');
            $table->foreignUuid('invoice_id')->nullable()->constrained('kiosk_invoices')->nullOnDelete();
            $table->dateTime('changed_at')->useCurrent();
            $table->text('note')->nullable();

            $table->index('article_id');
            $table->index('changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_price_change_log');
    }
};
