<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Konvertiert bestehende width/height in label_templates von Zoll nach cm.
 * 1 Zoll = 2.54 cm.
 *
 * Heuristik: Werte <= 10 sind vermutlich Zoll (alte Daten), darueber bleiben sie.
 * Die groessten DYMO-Etiketten haben ~8 Zoll = ~20 cm.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('label_templates')
            ->where('width', '<=', 10)
            ->update([
                'width'  => DB::raw('ROUND(width * 2.54, 2)'),
                'height' => DB::raw('ROUND(height * 2.54, 2)'),
            ]);
    }

    public function down(): void
    {
        DB::table('label_templates')
            ->where('width', '>', 10)
            ->update([
                'width'  => DB::raw('ROUND(width / 2.54, 2)'),
                'height' => DB::raw('ROUND(height / 2.54, 2)'),
            ]);
    }
};
