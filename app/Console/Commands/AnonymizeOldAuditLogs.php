<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Artisan-Befehl: Alte Audit-Logs anonymisieren.
 * DSGVO: Audit-Logs werden nach 3 Jahren anonymisiert.
 * IP-Adressen und User-Agents werden entfernt, personenbezogene
 * Daten in old_values/new_values werden geloescht.
 *
 * Ausfuehrung: php artisan rosi:anonymize-audit-logs
 * Geplant: Monatlich via Schedule
 */
class AnonymizeOldAuditLogs extends Command
{
    protected $signature = 'rosi:anonymize-audit-logs
                            {--dry-run : Nur anzeigen, nicht aendern}
                            {--years=3 : Jahre bis zur Anonymisierung}';

    protected $description = 'DSGVO: Alte Audit-Logs anonymisieren (nach 3 Jahren)';

    public function handle(): int
    {
        $years = (int) $this->option('years');
        $dryRun = $this->option('dry-run');
        $cutoffDate = now()->subYears($years);

        $this->info("Anonymisiere Audit-Logs aelter als {$cutoffDate->format('d.m.Y')}...");

        $count = AuditLog::where('created_at', '<', $cutoffDate)
            ->whereNotNull('ip_address')
            ->count();

        if ($count === 0) {
            $this->info('Keine Audit-Logs zum Anonymisieren gefunden.');
            return Command::SUCCESS;
        }

        $this->info("Gefunden: {$count} Audit-Logs zum Anonymisieren.");

        if ($dryRun) {
            $this->warn('DRY-RUN Modus - Es werden keine Daten geaendert.');
            return Command::SUCCESS;
        }

        // Batch-Update fuer Performance
        $updated = AuditLog::where('created_at', '<', $cutoffDate)
            ->whereNotNull('ip_address')
            ->update([
                'ip_address' => 'anonymisiert',
                'user_agent' => null,
                'old_values' => null,
                'new_values' => null,
            ]);

        $this->info("{$updated} Audit-Logs wurden anonymisiert.");

        return Command::SUCCESS;
    }
}
