<?php

namespace App\Console\Commands;

use App\Services\TemperaturePollService;
use Illuminate\Console\Command;

/**
 * Holt die aktuellen Kuehlmoebel-Temperaturen von der Mobile-Alerts-API,
 * speichert die Historie und aktualisiert Alarme. Laeuft per Server-Cron.
 */
class PollTemperatures extends Command
{
    protected $signature = 'temperatures:poll';

    protected $description = 'Kuehlmoebel-Temperaturen von Mobile Alerts abrufen, speichern und Alarme auswerten';

    public function handle(TemperaturePollService $service): int
    {
        $stats = $service->poll();

        $this->info(sprintf(
            'Temperatur-Poll: %d Moebel, %d Messungen gespeichert, %d Fehler.',
            $stats['units'], $stats['readings'], $stats['errors'],
        ));

        return self::SUCCESS;
    }
}
