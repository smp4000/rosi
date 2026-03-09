<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\AuditService;
use Illuminate\Console\Command;

/**
 * Artisan-Befehl: Abgelaufene Trial-Mandanten bereinigen.
 * DSGVO: Trial-Accounts werden nach 30 Tagen Inaktivitaet geloescht.
 *
 * Ausfuehrung: php artisan rosi:cleanup-trials
 * Geplant: Taeglich via Schedule
 */
class CleanupExpiredTrials extends Command
{
    protected $signature = 'rosi:cleanup-trials
                            {--dry-run : Nur anzeigen, nicht loeschen}
                            {--days=30 : Tage nach Trial-Ablauf bis zur Loesch ung}';

    protected $description = 'DSGVO: Abgelaufene Trial-Mandanten nach Karenzzeit loeschen';

    public function handle(AuditService $auditService): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');

        $this->info("Suche Trial-Mandanten die seit mehr als {$days} Tagen abgelaufen sind...");

        // Mandanten finden deren Trial vor mehr als X Tagen abgelaufen ist
        $expiredTenants = Tenant::where('subscription_status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now()->subDays($days))
            ->get();

        if ($expiredTenants->isEmpty()) {
            $this->info('Keine abgelaufenen Trial-Mandanten gefunden.');
            return Command::SUCCESS;
        }

        $this->info("Gefunden: {$expiredTenants->count()} abgelaufene Trial-Mandanten.");

        if ($dryRun) {
            $this->warn('DRY-RUN Modus - Es werden keine Daten geloescht.');
        }

        foreach ($expiredTenants as $tenant) {
            $this->line("  - {$tenant->name} (Trial abgelaufen: {$tenant->trial_ends_at->format('d.m.Y')})");

            if (! $dryRun) {
                // Audit-Eintrag VOR der Loesch ung
                $auditService->log(
                    action: 'delete',
                    auditable: $tenant,
                    oldValues: ['name' => $tenant->name, 'email' => $tenant->email],
                    reason: "DSGVO: Automatische Loesch ung nach {$days} Tagen Trial-Ablauf",
                );

                // Alle Benutzer des Mandanten soft-deleten
                $tenant->users()->delete();

                // Mandant soft-deleten
                $tenant->delete();

                $this->info("  ✓ Mandant '{$tenant->name}' geloescht.");
            }
        }

        $count = $expiredTenants->count();
        $action = $dryRun ? 'wuerden geloescht werden' : 'wurden geloescht';
        $this->info("{$count} Trial-Mandanten {$action}.");

        return Command::SUCCESS;
    }
}
