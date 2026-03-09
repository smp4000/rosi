<?php

namespace App\Console\Commands;

use App\Models\AiChatMessage;
use Illuminate\Console\Command;

/**
 * Artisan-Befehl: Alte KI-Chat-Verlaeufe loeschen.
 * DSGVO: KI-Chat-Verlaeufe werden nach 12 Monaten automatisch geloescht.
 *
 * Ausfuehrung: php artisan rosi:cleanup-ai-chats
 * Geplant: Monatlich via Schedule
 */
class CleanupAiChatMessages extends Command
{
    protected $signature = 'rosi:cleanup-ai-chats
                            {--dry-run : Nur anzeigen, nicht loeschen}
                            {--months=12 : Monate bis zur Loesch ung}';

    protected $description = 'DSGVO: Alte KI-Chat-Verlaeufe loeschen (nach 12 Monaten)';

    public function handle(): int
    {
        $months = (int) $this->option('months');
        $dryRun = $this->option('dry-run');
        $cutoffDate = now()->subMonths($months);

        $this->info("Loesche KI-Chat-Nachrichten aelter als {$cutoffDate->format('d.m.Y')}...");

        $count = AiChatMessage::where('created_at', '<', $cutoffDate)->count();

        if ($count === 0) {
            $this->info('Keine alten KI-Chat-Nachrichten gefunden.');
            return Command::SUCCESS;
        }

        $this->info("Gefunden: {$count} alte KI-Chat-Nachrichten.");

        if ($dryRun) {
            $this->warn('DRY-RUN Modus - Es werden keine Daten geloescht.');
            return Command::SUCCESS;
        }

        $deleted = AiChatMessage::where('created_at', '<', $cutoffDate)->delete();

        $this->info("{$deleted} KI-Chat-Nachrichten wurden geloescht.");

        return Command::SUCCESS;
    }
}
