<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Befuellt die shift_return_reasons-Tabelle mit Standard-Gruenden.
 * Diese sind fuer alle Tenants sichtbar (tenant_id = null, global).
 */
class ShiftReturnReasonsSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['label' => 'Saeulenverwechslung', 'sort_order' => 1],
            ['label' => 'Falscher Artikel Kunde wollte was anderes', 'sort_order' => 2],
            ['label' => 'Fehlende Materialien', 'sort_order' => 3],
        ];

        $now = now();

        foreach ($reasons as $data) {
            DB::table('shift_return_reasons')->updateOrInsert(
                ['label' => $data['label'], 'tenant_id' => null],
                [
                    'id'         => Str::uuid()->toString(),
                    'label'      => $data['label'],
                    'sort_order' => $data['sort_order'],
                    'is_active'  => true,
                    'tenant_id'  => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $this->command->info('  ' . count($reasons) . ' Standard-Ruecknahme-Gruende erstellt/aktualisiert.');
    }
}
