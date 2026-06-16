<?php

namespace App\Services;

use App\Models\GasStation;
use App\Models\LabelTemplate;
use App\Models\PrintJob;

/**
 * Zentrale Druck-Warteschlange. Statt direkt an einen DYMO zu drucken, wird
 * ein Job in die Queue gelegt; der "ROSI Print"-Agent am Stations-PC holt ihn
 * und druckt lokal. Vorteile: kein Firewall-/Netz-Thema, Audit, Retry, TTL.
 */
class PrintQueueService
{
    /**
     * Fertig gerenderte Labels in die Queue legen.
     *
     * @param  array  $labels  Liste aus XML-Strings ODER ['number' => ?, 'xml' => '...']
     * @param  array  $opts    reference, reference_type, printer_name, created_by, ttl_minutes,
     *                         target_agent_id (Ziel-Agent/Standort), user_id (Audit)
     */
    public function enqueue(GasStation $station, string $jobType, array $labels, array $opts = []): PrintJob
    {
        $payload = $this->normalizeLabels($labels);
        $printer = $opts['printer_name'] ?? $this->resolvePrinter($station, $jobType);
        $ttl = $opts['ttl_minutes'] ?? $this->ttlFor($jobType);

        return PrintJob::create([
            'tenant_id' => $station->tenant_id,
            'station_id' => $station->id,
            'job_type' => $jobType,
            'reference' => $opts['reference'] ?? null,
            'reference_type' => $opts['reference_type'] ?? null,
            'printer_name' => $printer,
            'target_agent_id' => $opts['target_agent_id'] ?? null,
            'payload' => $payload,
            'status' => PrintJob::STATUS_PENDING,
            'expires_at' => $ttl ? now()->addMinutes($ttl) : null,
            'created_by' => $opts['created_by'] ?? null,
            'user_id' => $opts['user_id'] ?? null,
        ]);
    }

    /**
     * Aus einer Label-Vorlage rendern und in die Queue legen.
     *
     * @param  array  $rows  Liste von Platzhalter-Daten (ein Datensatz = ein Etikett).
     *                       Optional pro Zeile '_number' fuer die Anzeige.
     */
    public function enqueueFromTemplate(GasStation $station, string $slugOrCategory, array $rows, array $opts = []): PrintJob
    {
        $template = LabelTemplate::findForTenant($slugOrCategory, $station->tenant_id);
        if (! $template) {
            throw new \RuntimeException("Keine Druckvorlage fuer '{$slugOrCategory}' gefunden.");
        }

        $labels = [];
        foreach (array_values($rows) as $i => $row) {
            $labels[] = [
                'number' => $row['_number'] ?? ($i + 1),
                'xml' => $template->render($row),
            ];
        }

        $jobType = $opts['job_type'] ?? $template->category ?? $slugOrCategory;

        return $this->enqueue($station, $jobType, $labels, $opts);
    }

    // ── intern ───────────────────────────────────────

    /** @return array<int, array{number: int|string|null, xml: string}> */
    private function normalizeLabels(array $labels): array
    {
        $out = [];
        foreach (array_values($labels) as $i => $label) {
            if (is_string($label)) {
                $out[] = ['number' => $i + 1, 'xml' => $label];
            } elseif (is_array($label) && isset($label['xml'])) {
                $out[] = ['number' => $label['number'] ?? ($i + 1), 'xml' => $label['xml']];
            }
        }

        return $out;
    }

    /** Drucker aus dem Station-Mapping bestimmen (job_type -> '*' -> null). */
    private function resolvePrinter(GasStation $station, string $jobType): ?string
    {
        $map = $station->printer_map ?? [];

        return $map[$jobType] ?? $map['*'] ?? null;
    }

    private function ttlFor(string $jobType): int
    {
        return (int) (config("printing.ttl_minutes.{$jobType}")
            ?? config('printing.default_ttl_minutes', 15));
    }
}
