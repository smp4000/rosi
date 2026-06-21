<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pro Geraet (MDE/Handy) einen Standard-Drucker + Alternativen festlegen.
 * So bekommt das Geraet in der App nicht ALLE Drucker aller Agenten gezeigt,
 * sondern nur die hinterlegte Auswahl. Bearbeitbar im Dashboard UND in der App.
 * Schluessel-Format: "<agent_id>|<printer_name>".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('print_default')->nullable()->after('app_version');
            $table->json('print_alternatives')->nullable()->after('print_default');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['print_default', 'print_alternatives']);
        });
    }
};
