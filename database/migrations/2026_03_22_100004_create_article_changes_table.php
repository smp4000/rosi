<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Artikel-Aenderungshistorie.
     * Loggt jede Aenderung beim CSV-Import (Preise, Daten, Status).
     */
    public function up(): void
    {
        Schema::create('article_changes', function (Blueprint $table) {
            $table->id()->comment('Auto-Increment');
            $table->foreignUuid('article_id')->constrained('articles')->cascadeOnDelete()->comment('Betroffener Artikel');
            $table->uuid('article_import_id')->nullable()->comment('Durch welchen Import ausgeloest');

            $table->enum('change_type', ['created', 'price_updated', 'data_updated', 'reactivated'])->comment('Art der Aenderung');
            $table->string('field_name', 100)->nullable()->comment('Geaendertes Feld (z.B. selling_price)');
            $table->string('old_value', 500)->nullable()->comment('Alter Wert');
            $table->string('new_value', 500)->nullable()->comment('Neuer Wert');

            $table->timestamp('created_at')->useCurrent()->comment('Zeitpunkt der Aenderung');

            // Foreign Key
            $table->foreign('article_import_id')->references('id')->on('article_imports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_changes');
    }
};
