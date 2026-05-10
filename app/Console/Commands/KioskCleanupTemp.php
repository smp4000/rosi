<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Loescht alte Livewire-Temp-Uploads (PDFs + .json-Sidecars) die
 * aelter als die angegebene Stundenzahl sind.
 *
 * php artisan kiosk:cleanup-temp [--hours=24]
 *
 * Sinnvoll als Cron-Eintrag damit das livewire-tmp Verzeichnis
 * nicht voll laeuft.
 */
class KioskCleanupTemp extends Command
{
    protected $signature = 'kiosk:cleanup-temp {--hours=24}';

    protected $description = 'Loescht alte livewire-tmp Uploads aelter als N Stunden (Default 24).';

    public function handle(): int
    {
        $hours = max(0, (int) $this->option('hours'));
        $cutoff = $hours === 0 ? time() + 1 : time() - ($hours * 3600);

        $dirs = [
            storage_path('app/private/livewire-tmp'),
            storage_path('app/livewire-tmp'),
        ];

        $totalSize = 0;
        $totalCount = 0;

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) continue;
            foreach (glob($dir . '/*') ?: [] as $file) {
                if (! is_file($file)) continue;
                if (filemtime($file) < $cutoff) {
                    $totalSize += filesize($file);
                    @unlink($file);
                    $totalCount++;
                }
            }
        }

        $mb = round($totalSize / 1024 / 1024, 2);
        $this->info("Geloescht: {$totalCount} Dateien ({$mb} MB), aelter als {$hours}h.");
        return self::SUCCESS;
    }
}
