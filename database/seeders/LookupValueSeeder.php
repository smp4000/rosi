<?php

namespace Database\Seeders;

use App\Models\LookupValue;
use Illuminate\Database\Seeder;

/**
 * Vordefinierte Werte fuer Lookup-Dropdowns.
 */
class LookupValueSeeder extends Seeder
{
    public function run(): void
    {
        $values = [
            // Waschanlage-Marken
            ['category' => 'car_wash_brand', 'value' => 'WashTec', 'sort_order' => 1],
            ['category' => 'car_wash_brand', 'value' => 'Christ', 'sort_order' => 2],
            ['category' => 'car_wash_brand', 'value' => 'Otto Christ', 'sort_order' => 3],
            ['category' => 'car_wash_brand', 'value' => 'Kaercher', 'sort_order' => 4],
            ['category' => 'car_wash_brand', 'value' => 'Istobal', 'sort_order' => 5],
            ['category' => 'car_wash_brand', 'value' => 'Ehrle', 'sort_order' => 6],
            ['category' => 'car_wash_brand', 'value' => 'Ceccato', 'sort_order' => 7],

            // Waschanlage-Typen
            ['category' => 'car_wash_type', 'value' => 'Portalwaschanlage', 'sort_order' => 1],
            ['category' => 'car_wash_type', 'value' => 'Waschstrasse', 'sort_order' => 2],
            ['category' => 'car_wash_type', 'value' => 'SB-Waschbox', 'sort_order' => 3],
            ['category' => 'car_wash_type', 'value' => 'Kombi (Portal + SB)', 'sort_order' => 4],
            ['category' => 'car_wash_type', 'value' => 'Buerstenwaschanlage', 'sort_order' => 5],
            ['category' => 'car_wash_type', 'value' => 'Buerstenlos', 'sort_order' => 6],
        ];

        foreach ($values as $value) {
            LookupValue::updateOrCreate(
                ['category' => $value['category'], 'value' => $value['value']],
                ['sort_order' => $value['sort_order']]
            );
        }
    }
}
