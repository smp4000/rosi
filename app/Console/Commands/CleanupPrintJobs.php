<?php

namespace App\Console\Commands;

use App\Models\PrintJob;
use Illuminate\Console\Command;

/**
 * Haelt die Druck-Warteschlange sauber:
 *  - ueberfaellige 'pending'-Jobs (TTL abgelaufen) -> 'expired' (kein Nachdruck)
 *  - haengende 'printing'-Jobs (Agent abgestuerzt) -> zurueck in die Queue
 *    bzw. 'expired', falls TTL inzwischen abgelaufen
 *  - alte erledigte/abgelaufene/fehlgeschlagene Jobs loeschen (Aufbewahrung)
 */
class CleanupPrintJobs extends Command
{
    protected $signature = 'print:cleanup {--stuck-minutes=2 : Ab wann ein haengender printing-Job zurueck in die Queue geht}';

    protected $description = 'Druck-Warteschlange aufraeumen (TTL/Expiry, haengende Jobs, alte Jobs loeschen)';

    public function handle(): int
    {
        $now = now();

        // 1) Ueberfaellige pending -> expired
        $expired = PrintJob::where('status', PrintJob::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->update(['status' => PrintJob::STATUS_EXPIRED]);

        // 2) Haengende printing-Jobs (Agent weg) wieder freigeben
        $stuckThreshold = $now->copy()->subMinutes((int) $this->option('stuck-minutes'));
        $stuckJobs = PrintJob::where('status', PrintJob::STATUS_PRINTING)
            ->where('updated_at', '<', $stuckThreshold)
            ->get();

        $requeued = 0;
        $stuckExpired = 0;
        foreach ($stuckJobs as $job) {
            if ($job->isExpired()) {
                $job->update(['status' => PrintJob::STATUS_EXPIRED]);
                $stuckExpired++;
            } else {
                $job->update(['status' => PrintJob::STATUS_PENDING, 'agent_id' => null]);
                $requeued++;
            }
        }

        // 3) Alte Endzustaende loeschen (Audit liegt separat im Druckprotokoll)
        $retentionDays = (int) config('printing.retention_days', 7);
        $deleted = PrintJob::whereIn('status', [
                PrintJob::STATUS_DONE,
                PrintJob::STATUS_EXPIRED,
                PrintJob::STATUS_FAILED,
            ])
            ->where('updated_at', '<', $now->copy()->subDays($retentionDays))
            ->delete();

        $this->info("Abgelaufen: {$expired} · Requeued: {$requeued} · haengend-abgelaufen: {$stuckExpired} · geloescht: {$deleted}");

        return self::SUCCESS;
    }
}
