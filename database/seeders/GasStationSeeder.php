<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\GasStation;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Erstellt 2 reale Aral-Tankstellen von Christian Welle in Fulda.
 */
class GasStationSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->command->error('Kein Tenant gefunden. Bitte zuerst DatabaseSeeder ausfuehren.');
            return;
        }

        $aral = Brand::where('name', 'Aral')->first();

        if (! $aral) {
            $this->command->error('Marke "Aral" nicht gefunden. Bitte zuerst BrandSeeder ausfuehren.');
            return;
        }

        // --- Tankstelle 1: Schlitzer Str. 105 ---
        GasStation::updateOrCreate(
            ['street' => 'Schlitzer Str.', 'house_number' => '105', 'zip' => '36039'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Aral Tankstelle Christian Welle',
                'brand_id' => $aral->id,
                'sales_channel' => 'Eigengeschäft',
                'ownership_type' => 'Eigentum',
                'street' => 'Schlitzer Str.',
                'house_number' => '105',
                'zip' => '36039',
                'city' => 'Fulda',
                'state' => 'Hessen',
                'country' => 'DE',
                'latitude' => 50.56160,
                'longitude' => 9.66580,
                'contact_first_name' => 'Christian',
                'contact_last_name' => 'Welle',
                'phone' => '066151681',
                'email' => 'sv.welle@aral-welle.de',
                'has_shop' => true,
                'shop_partner' => 'Rewe To Go',
                'has_car_wash' => true,
                'car_wash_details' => [
                    'type' => 'Portal',
                    'manufacturer' => 'WashTec',
                ],
                'fuel_types' => [
                    'Super E5',
                    'Super E10',
                    'Diesel',
                    'Super Plus',
                ],
                'opening_hours' => [
                    'monday'    => ['open' => '06:00', 'close' => '21:00'],
                    'tuesday'   => ['open' => '06:00', 'close' => '21:00'],
                    'wednesday' => ['open' => '06:00', 'close' => '21:00'],
                    'thursday'  => ['open' => '06:00', 'close' => '21:00'],
                    'friday'    => ['open' => '06:00', 'close' => '21:00'],
                    'saturday'  => ['open' => '07:00', 'close' => '21:00'],
                    'sunday'    => ['open' => '08:00', 'close' => '21:00'],
                ],
                'is_active' => true,
            ],
        );

        // --- Tankstelle 2: Petersberger Str. 101 ---
        GasStation::updateOrCreate(
            ['street' => 'Petersberger Straße', 'house_number' => '101', 'zip' => '36100'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Aral Tankstelle Christian Welle',
                'brand_id' => $aral->id,
                'sales_channel' => 'Eigengeschäft',
                'ownership_type' => 'Eigentum',
                'street' => 'Petersberger Straße',
                'house_number' => '101',
                'zip' => '36100',
                'city' => 'Fulda',
                'state' => 'Hessen',
                'country' => 'DE',
                'latitude' => 50.55420,
                'longitude' => 9.69240,
                'contact_first_name' => 'Christian',
                'contact_last_name' => 'Welle',
                'phone' => '066165535',
                'email' => 'sv.welle@aral-welle.de',
                'has_shop' => true,
                'has_car_wash' => true,
                'car_wash_details' => [
                    'type' => 'Portal',
                    'manufacturer' => 'WashTec',
                ],
                'fuel_types' => [
                    'Super E5',
                    'Super E10',
                    'Diesel',
                    'Aral Ultimate 102',
                    'Aral Ultimate Diesel',
                ],
                'opening_hours' => [
                    'monday'    => ['open' => '06:00', 'close' => '22:00'],
                    'tuesday'   => ['open' => '06:00', 'close' => '22:00'],
                    'wednesday' => ['open' => '06:00', 'close' => '22:00'],
                    'thursday'  => ['open' => '06:00', 'close' => '22:00'],
                    'friday'    => ['open' => '06:00', 'close' => '22:00'],
                    'saturday'  => ['open' => '07:00', 'close' => '22:00'],
                    'sunday'    => ['open' => '08:00', 'close' => '22:00'],
                ],
                'is_active' => true,
            ],
        );

        $this->command->info('2 Aral-Tankstellen (Christian Welle, Fulda) erstellt.');
    }
}
