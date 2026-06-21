<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Korrigiert vertauschte Soll-Grenzen (z.B. Minusgrade "-14 bis -18"):
 * target_min muss <= target_max sein.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('cooling_units')
            ->whereNotNull('target_min')
            ->whereNotNull('target_max')
            ->whereColumn('target_min', '>', 'target_max')
            ->get(['id', 'target_min', 'target_max']);

        foreach ($rows as $r) {
            DB::table('cooling_units')->where('id', $r->id)->update([
                'target_min' => $r->target_max,
                'target_max' => $r->target_min,
            ]);
        }
    }

    public function down(): void
    {
        // Keine Rueckabwicklung (Datenkorrektur).
    }
};
