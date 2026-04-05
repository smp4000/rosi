<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Befuellt die depreciation_reasons-Tabelle mit Standard-Abschreibungsgruenden.
 * Diese sind fuer alle Tenants sichtbar (tenant_id = null, is_default = true).
 */
class DepreciationReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            ['id' => 2,  'name' => 'Diebstahl',                          'status' => false],
            ['id' => 3,  'name' => 'Bruch',                              'status' => true],
            ['id' => 4,  'name' => 'Verderb',                            'status' => true],
            ['id' => 5,  'name' => 'MHD-Überschreitung',                 'status' => true],
            ['id' => 6,  'name' => 'Schwund',                            'status' => false],
            ['id' => 7,  'name' => 'Eigenverbrauch (Chef/Chefin)',       'status' => true],
            ['id' => 8,  'name' => 'betriebsb. Entnahme',               'status' => false],
            ['id' => 10, 'name' => 'Sonstiges',                          'status' => false],
            ['id' => 11, 'name' => 'Verbrauch HuV',                      'status' => true],
            ['id' => 12, 'name' => 'Standzeit Bistro',                   'status' => true],
            ['id' => 13, 'name' => 'Planogramm Änderung',                'status' => true],
            ['id' => 14, 'name' => 'Lieferung in andere Betriebsstätte', 'status' => true],
            ['id' => 15, 'name' => 'TooGoodToGo',                        'status' => false],
        ];

        $now = now();

        foreach ($reasons as $data) {
            DB::table('depreciation_reasons')->updateOrInsert(
                ['id' => $data['id']],
                [
                    'tenant_id'   => null,
                    'name'        => $data['name'],
                    'description' => null,
                    'status'      => $data['status'],
                    'is_default'  => true,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
            );
        }

        $this->command->info('  ' . count($reasons) . ' Standard-Abschreibungsgründe erstellt/aktualisiert.');
    }
}
