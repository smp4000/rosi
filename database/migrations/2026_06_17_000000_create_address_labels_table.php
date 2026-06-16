<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gespeicherte Adress-Etiketten (Brief-Etiketten) zum mehrfachen Nachdrucken.
 * Der Partner waehlt eine seiner Stationen als Absender und gibt die
 * Empfaengeradresse ein; das Etikett wird gespeichert und kann an
 * verschiedenen Tagen erneut gedruckt werden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('address_labels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('sender_station_id')->nullable()->comment('Station als Absender');
            $table->string('sender_text')->comment('Absender-Zeile (Snapshot)');
            $table->string('recipient_name');
            $table->string('recipient_street')->nullable();
            $table->string('recipient_city')->nullable();
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();
            $table->string('created_by')->nullable()->comment('Name des Erstellers');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('address_labels');
    }
};
