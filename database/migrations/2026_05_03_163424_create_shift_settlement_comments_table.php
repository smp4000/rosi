<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nachtraegliche Kommentare zu Schichtabrechnungen.
 * Mitarbeiter kann auch nach Abschluss einer Schicht Kommentare
 * hinzufuegen, ohne die Original-Daten zu veraendern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_settlement_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shift_settlement_id')->constrained('shift_settlements')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('shift_settlement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_settlement_comments');
    }
};
