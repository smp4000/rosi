<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kamera-Feld fuer Tankstellen.
 * Wird im Tankbetrug-Formular zur Vorbelegung "Videoanlage nicht vorhanden" genutzt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            $table->boolean('has_camera')->default(true)
                ->after('num_pumps')
                ->comment('Hat die Tankstelle eine Videoueberwachung?');
        });
    }

    public function down(): void
    {
        Schema::table('gas_stations', function (Blueprint $table) {
            $table->dropColumn('has_camera');
        });
    }
};
