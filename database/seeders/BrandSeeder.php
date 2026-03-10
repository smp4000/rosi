<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

/**
 * Befuellt die brands-Tabelle mit allen gaengigen deutschen Tankstellen-Marken.
 */
class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            ['name' => 'Aral', 'sort_order' => 1],
            ['name' => 'Shell', 'sort_order' => 2],
            ['name' => 'TotalEnergies', 'sort_order' => 3],
            ['name' => 'ESSO', 'sort_order' => 4],
            ['name' => 'JET', 'sort_order' => 5],
            ['name' => 'ENI (Agip)', 'sort_order' => 6],
            ['name' => 'OMV', 'sort_order' => 7],
            ['name' => 'Orlen (Star)', 'sort_order' => 8],
            ['name' => 'Westfalen', 'sort_order' => 9],
            ['name' => 'HEM', 'sort_order' => 10],
            ['name' => 'OIL!', 'sort_order' => 11],
            ['name' => 'Sprint', 'sort_order' => 12],
            ['name' => 'bft', 'sort_order' => 13],
            ['name' => 'AVIA', 'sort_order' => 14],
            ['name' => 'Q1', 'sort_order' => 15],
            ['name' => 'Raiffeisen', 'sort_order' => 16],
            ['name' => 'Globus', 'sort_order' => 17],
            ['name' => 'classic', 'sort_order' => 18],
            ['name' => 'Calpam', 'sort_order' => 19],
            ['name' => 'Hoyer', 'sort_order' => 20],
            ['name' => 'Nordoel', 'sort_order' => 21],
            ['name' => 'Hessol', 'sort_order' => 22],
            ['name' => 'go', 'sort_order' => 23],
            ['name' => 'Gulf', 'sort_order' => 24],
            ['name' => 'SB Tankstelle', 'sort_order' => 25],
            ['name' => 'team', 'sort_order' => 26],
            ['name' => 'Baywa', 'sort_order' => 27],
            ['name' => 'Roth', 'sort_order' => 28],
            ['name' => 'Supermarkt-Tankstelle', 'sort_order' => 29],
            ['name' => 'Freie Tankstelle', 'sort_order' => 99],
        ];

        foreach ($brands as $data) {
            Brand::firstOrCreate(
                ['name' => $data['name']],
                $data,
            );
        }

        $this->command->info('  ' . count($brands) . ' Tankstellen-Marken erstellt/aktualisiert.');
    }
}
