<?php

namespace Database\Seeders;

use App\Models\CoolingMeasure;
use Illuminate\Database\Seeder;

/**
 * Maßnahmenkatalog Temperaturkontrolle (ARAL HQM 7.0, 12/2022).
 * Systemweite Eintraege (tenant_id null).
 */
class CoolingMeasureSeeder extends Seeder
{
    public function run(): void
    {
        $measures = [
            1 => '30 Minuten später erneute Messung, Temperatur in Ordnung',
            2 => 'Ware entsorgt',
            3 => 'Thermostateinstellung kontrolliert; Einstellung korrigiert',
            4 => 'Verdampfer gereinigt',
            5 => 'Kontraktor informiert; Störmeldung abgegeben und Ticketnummer im Formblatt Temperaturprüfung notiert',
            6 => 'Gerät gerade neu bestückt',
            7 => 'Abtauphase',
            8 => 'Gerät zu voll; Luft / Dampf konnte nicht zirkulieren; Ware auf mehrere Geräte verteilt; Temperatur jetzt in Ordnung',
            9 => 'Gerät momentan nicht in Nutzung',
        ];

        foreach ($measures as $number => $text) {
            CoolingMeasure::query()->updateOrCreate(
                ['tenant_id' => null, 'number' => $number],
                ['text' => $text, 'is_system' => true, 'is_active' => true, 'sort' => $number],
            );
        }
    }
}
