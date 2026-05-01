<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konfigurierbare Prueffragen fuer Schichtabrechnungen.
 * Werden im Admin-Panel verwaltet und in der App angezeigt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_settlement_check_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('gas_station_id')->nullable()->constrained()->nullOnDelete()
                ->comment('NULL = gilt fuer alle Stationen');

            $table->string('question_text')->comment('Fragetext');
            $table->enum('question_type', ['yes_no', 'yes_no_comment', 'text', 'number'])
                ->default('yes_no')
                ->comment('yes_no=Ja/Nein, yes_no_comment=Ja/Nein+Pflichtkommentar bei Nein, text=Freitext, number=Zahl');
            $table->unsignedInteger('sort_order')->default(0)->comment('Reihenfolge');
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_settlement_check_questions');
    }
};
