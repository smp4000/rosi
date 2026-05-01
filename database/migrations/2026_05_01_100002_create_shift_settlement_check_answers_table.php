<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antworten auf Prueffragen pro Schichtabrechnung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_settlement_check_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('shift_settlement_id')->constrained('shift_settlements')->cascadeOnDelete();
            $table->foreignUuid('question_id')->constrained('shift_settlement_check_questions')->cascadeOnDelete();

            $table->boolean('answer_bool')->nullable()->comment('Ja/Nein Antwort');
            $table->text('answer_text')->nullable()->comment('Freitext-Antwort oder Kommentar');
            $table->decimal('answer_number', 10, 2)->nullable()->comment('Numerische Antwort');

            $table->timestamps();

            $table->index('shift_settlement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_settlement_check_answers');
    }
};
