<?php

namespace App\Console\Commands;

use App\Models\Kiosk\Article;
use Illuminate\Console\Command;

/**
 * Backfill Wochentage fuer bestehende Kiosk-Artikel
 * (Bezeichnung-Pattern: "1Mo", "2Di", etc.)
 *
 * Aufruf: php artisan kiosk:backfill-weekdays
 */
class KioskBackfillWeekdays extends Command
{
    protected $signature = 'kiosk:backfill-weekdays';

    protected $description = 'Setzt das weekday-Feld fuer Kiosk-Artikel anhand der Bezeichnung.';

    public function handle(): int
    {
        $this->info('Backfill Wochentage fuer Kiosk-Artikel...');

        $count = 0;
        $map = ['Mo' => 1, 'Di' => 2, 'Mi' => 3, 'Do' => 4, 'Fr' => 5, 'Sa' => 6, 'So' => 7];

        Article::whereNull('weekday')->orderBy('objekt')->chunk(100, function ($articles) use (&$count, $map) {
            foreach ($articles as $article) {
                $weekday = null;

                if (preg_match('/\b([1-7])(Mo|Di|Mi|Do|Fr|Sa|So)\b/u', $article->bezeichnung, $m)) {
                    $weekday = (int) $m[1];
                } elseif (preg_match('/\b(Mo|Di|Mi|Do|Fr|Sa|So)\b\s*$/u', $article->bezeichnung, $m)) {
                    $weekday = $map[$m[1]] ?? null;
                }

                if ($weekday !== null) {
                    $article->update(['weekday' => $weekday]);
                    $count++;
                    $this->line("  {$article->objekt} {$article->bezeichnung} -> {$weekday}");
                }
            }
        });

        $this->info("Fertig. {$count} Artikel aktualisiert.");
        return self::SUCCESS;
    }
}
