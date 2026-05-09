<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ausgaben (z.B. Kalenderwochen) eines Kiosk-Artikels.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_article_issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('article_id')->constrained('kiosk_articles')->cascadeOnDelete();
            $table->string('ausgabe', 10)->comment('Kalenderwoche, z.B. "0019"');
            $table->string('ean_addon', 10)->nullable()->comment('5-stelliger EAN-Zusatz');
            $table->dateTime('first_seen_at')->useCurrent();
            $table->dateTime('last_seen_at')->nullable();

            $table->unique(['article_id', 'ausgabe'], 'uq_kiosk_article_ausgabe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_article_issues');
    }
};
