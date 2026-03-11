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
            ['name' => 'Aral', 'slug' => 'aral', 'sort_order' => 1],
            ['name' => 'Shell', 'slug' => 'shell', 'sort_order' => 2],
            ['name' => 'TotalEnergies', 'slug' => 'totalenergies', 'sort_order' => 3],
            ['name' => 'ESSO', 'slug' => 'esso', 'sort_order' => 4],
            ['name' => 'JET', 'slug' => 'jet', 'sort_order' => 5],
            ['name' => 'ENI (Agip)', 'slug' => 'eni-agip', 'sort_order' => 6],
            ['name' => 'OMV', 'slug' => 'omv', 'sort_order' => 7],
            ['name' => 'Orlen (Star)', 'slug' => 'orlen-star', 'sort_order' => 8],
            ['name' => 'Westfalen', 'slug' => 'westfalen', 'sort_order' => 9],
            ['name' => 'HEM', 'slug' => 'hem', 'sort_order' => 10],
            ['name' => 'OIL!', 'slug' => 'oil', 'sort_order' => 11],
            ['name' => 'Sprint', 'slug' => 'sprint', 'sort_order' => 12],
            ['name' => 'bft', 'slug' => 'bft', 'sort_order' => 13],
            ['name' => 'AVIA', 'slug' => 'avia', 'sort_order' => 14],
            ['name' => 'Q1', 'slug' => 'q1', 'sort_order' => 15],
            ['name' => 'Raiffeisen', 'slug' => 'raiffeisen', 'sort_order' => 16],
            ['name' => 'Globus', 'slug' => 'globus', 'sort_order' => 17],
            ['name' => 'classic', 'slug' => 'classic', 'sort_order' => 18],
            ['name' => 'Calpam', 'slug' => 'calpam', 'sort_order' => 19],
            ['name' => 'Hoyer', 'slug' => 'hoyer', 'sort_order' => 20],
            ['name' => 'Nordoel', 'slug' => 'nordoel', 'sort_order' => 21],
            ['name' => 'Hessol', 'slug' => 'hessol', 'sort_order' => 22],
            ['name' => 'go', 'slug' => 'go', 'sort_order' => 23],
            ['name' => 'Gulf', 'slug' => 'gulf', 'sort_order' => 24],
            ['name' => 'SB Tankstelle', 'slug' => 'sb-tankstelle', 'sort_order' => 25],
            ['name' => 'team', 'slug' => 'team', 'sort_order' => 26],
            ['name' => 'Baywa', 'slug' => 'baywa', 'sort_order' => 27],
            ['name' => 'Roth', 'slug' => 'roth', 'sort_order' => 28],
            ['name' => 'Supermarkt-Tankstelle', 'slug' => 'supermarkt-tankstelle', 'sort_order' => 29],
            ['name' => 'Freie Tankstelle', 'slug' => 'freie-tankstelle', 'sort_order' => 99],
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
