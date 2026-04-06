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

        // --- Tankstelle 1: Schlitzer Str. 105 (Fulda) ---
        GasStation::updateOrCreate(
            ['street' => 'Schlitzer Str.', 'house_number' => '105', 'zip' => '36039'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Aral Tankstelle Welle Fulda',
                'brand_id' => $aral->id,
                'sales_channel' => 'Eigengeschäft',
                'ownership_type' => 'DOFO',
                'district' => '11',
                'district_description' => 'TD 11 - DODO Nord',
                'region' => '06',
                'region_manager' => 'Johannes Diemert',
                'station_number_fuel' => '0F671',
                'station_number_shop' => '170320196',
                'has_toll_terminal' => false,
                'street' => 'Schlitzer Str.',
                'house_number' => '105',
                'zip' => '36039',
                'city' => 'Fulda',
                'state' => 'Hessen',
                'country' => 'DE',
                'latitude' => 50.56526760,
                'longitude' => 9.65713830,
                'contact_first_name' => 'Christian',
                'contact_last_name' => 'Welle',
                'phone' => '066151681',
                'email' => 'sv.welle@aral-welle.de',
                'num_pumps' => 8,
                'has_shop' => true,
                'shop_size' => '60',
                'shop_type' => 'rewe_to_go',
                'assortment_level' => 'premium',
                'shop_partner' => 'Rewe To Go',
                'shop_operation_number' => '0F671',
                'has_car_wash' => true,
                'car_wash_details' => [
                    'has_drive_through' => true,
                    'has_underbody_wash' => false,
                    'brand_id' => 1,
                    'type_id' => null,
                    'height' => 2.4,
                    'width' => 2.3,
                    'has_ticket_system' => true,
                    'has_easy_carwash_pro' => true,
                    'notes' => null,
                ],
                'opening_hours' => [
                    'is_24h' => false,
                    'monday'    => ['open' => '06:00', 'close' => '21:00'],
                    'tuesday'   => ['open' => '06:00', 'close' => '21:00'],
                    'wednesday' => ['open' => '06:00', 'close' => '21:00'],
                    'thursday'  => ['open' => '06:00', 'close' => '21:00'],
                    'friday'    => ['open' => '06:00', 'close' => '21:00'],
                    'saturday'  => ['open' => '07:00', 'close' => '21:00'],
                    'sunday'    => ['open' => '08:00', 'close' => '21:00'],
                ],
                'services' => ['bakery', 'cafe'],
                'fuel_types' => ['Super E5', 'Super E10', 'Diesel', 'Super Plus'],
                'additional_businesses' => ['hermes', 'toto', 'lotto'],
                'competitors' => [
                    [
                        'name' => 'ARAL Petersberg',
                        'brand' => 'ARAL',
                        'street' => 'Petersberger Straße 101',
                        'city' => 'Petersberg',
                        'zip' => '36100',
                        'distance' => 2.7,
                        'lat' => 50.5525475,
                        'lng' => 9.7019444,
                        'price_e10' => '2.229',
                        'price_diesel' => '2.459',
                        'price_super' => '2.289',
                        'notes' => null,
                    ],
                ],
                'is_active' => true,
            ],
        );

        // --- Tankstelle 2: Petersberger Str. 101 (Petersberg) ---
        GasStation::updateOrCreate(
            ['street' => 'Petersberger Straße', 'house_number' => '101', 'zip' => '36100'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Aral Tankstelle Welle Petersberg',
                'brand_id' => $aral->id,
                'sales_channel' => 'Eigengeschäft',
                'ownership_type' => 'DODO',
                'district' => '11',
                'district_description' => 'TD 11 - DODO Nord',
                'region' => '06',
                'region_manager' => 'Johannes Diemert',
                'station_number_fuel' => '0F163',
                'station_number_shop' => '12146900',
                'has_toll_terminal' => false,
                'street' => 'Petersberger Straße',
                'house_number' => '101',
                'zip' => '36100',
                'city' => 'Fulda',
                'state' => 'Hessen',
                'country' => 'DE',
                'latitude' => 50.55420000,
                'longitude' => 9.69240000,
                'contact_first_name' => 'Christian',
                'contact_last_name' => 'Welle',
                'phone' => '066165535',
                'email' => 'sv.welle@aral-welle.de',
                'num_pumps' => 8,
                'has_shop' => true,
                'shop_size' => '150',
                'shop_type' => 'bistro',
                'shop_partner' => '90034000',
                'has_car_wash' => true,
                'car_wash_details' => [
                    'has_drive_through' => true,
                    'has_underbody_wash' => true,
                    'brand_id' => 1,
                    'type_id' => 8,
                    'height' => 2.4,
                    'width' => 2.3,
                    'has_ticket_system' => true,
                    'has_easy_carwash_pro' => true,
                    'notes' => null,
                ],
                'opening_hours' => [
                    'is_24h' => false,
                    'monday'    => ['open' => '06:00', 'close' => '22:00'],
                    'tuesday'   => ['open' => '06:00', 'close' => '22:00'],
                    'wednesday' => ['open' => '06:00', 'close' => '22:00'],
                    'thursday'  => ['open' => '06:00', 'close' => '22:00'],
                    'friday'    => ['open' => '06:00', 'close' => '22:00'],
                    'saturday'  => ['open' => '07:00', 'close' => '22:00'],
                    'sunday'    => ['open' => '08:00', 'close' => '22:00'],
                ],
                'services' => ['cafe', 'bakery'],
                'fuel_types' => ['Super E5', 'Super E10', 'Diesel', 'Aral Ultimate 102', 'Aral Ultimate Diesel'],
                'additional_businesses' => ['hermes', 'lotto', 'toto'],
                'competitors' => [
                    [
                        'name' => 'AVIA Petersberg',
                        'brand' => 'AVIA',
                        'street' => 'Breitunger Straße 2',
                        'city' => 'Petersberg',
                        'zip' => '36100',
                        'distance' => 2.1,
                        'lat' => 50.5550995,
                        'lng' => 9.7214279,
                        'price_e10' => '2.199',
                        'price_diesel' => '2.439',
                        'price_super' => '2.249',
                        'notes' => null,
                    ],
                    [
                        'name' => 'Fulda Petersberg',
                        'brand' => null,
                        'street' => 'Justus Liebig Straße 9',
                        'city' => 'Fulda Petersberg',
                        'zip' => '36100',
                        'distance' => 2.4,
                        'lat' => 50.5525284,
                        'lng' => 9.72651,
                        'price_e10' => '2.209',
                        'price_diesel' => '2.419',
                        'price_super' => '2.229',
                        'notes' => null,
                    ],
                    [
                        'name' => 'OIL! Künzell',
                        'brand' => 'OIL!',
                        'street' => 'Unterer Ortesweg 1',
                        'city' => 'Künzell',
                        'zip' => '36093',
                        'distance' => 1.7,
                        'lat' => 50.5447006,
                        'lng' => 9.7115698,
                        'price_e10' => '2.194',
                        'price_diesel' => '2.436',
                        'price_super' => '2.249',
                        'notes' => null,
                    ],
                    [
                        'name' => 'OIL! Fulda',
                        'brand' => 'OIL!',
                        'street' => 'Künzeller Str. 64',
                        'city' => 'Fulda',
                        'zip' => '36043',
                        'distance' => 0.9,
                        'lat' => 50.5463982,
                        'lng' => 9.6887999,
                        'price_e10' => '2.195',
                        'price_diesel' => '2.436',
                        'price_super' => '2.250',
                        'notes' => null,
                    ],
                    [
                        'name' => 'Raiffeisen Fulda',
                        'brand' => 'Raiffeisen',
                        'street' => 'Petersberger Str. 42',
                        'city' => 'Fulda',
                        'zip' => '36037',
                        'distance' => 0.6,
                        'lat' => 50.5500793,
                        'lng' => 9.6862993,
                        'price_e10' => '2.179',
                        'price_diesel' => '2.429',
                        'price_super' => '2.239',
                        'notes' => null,
                    ],
                ],
                'is_active' => true,
            ],
        );

        $this->command->info('2 Aral-Tankstellen (Christian Welle, Fulda) erstellt.');
    }
}
