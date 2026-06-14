<?php

namespace App\Services;

use App\Models\DepreciationEntry;
use App\Models\GasStation;
use App\Models\GeneratedReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Erzeugt PDF-Berichte (aktuell: Abschriften-Tagesbericht) und archiviert sie
 * in der Tabelle generated_reports. Die Datei liegt auf disk 'local'.
 */
class ReportService
{
    /**
     * Abschriften-Bericht fuer einen Zeitraum (optional je Station) erzeugen.
     *
     * @return GeneratedReport
     */
    public function generateDepreciationReport(
        string $tenantId,
        ?string $stationId,
        Carbon $from,
        Carbon $to,
    ): GeneratedReport {
        $entries = DepreciationEntry::query()
            ->where('tenant_id', $tenantId)
            ->when($stationId, fn ($q) => $q->where('station_id', $stationId))
            ->whereBetween('recorded_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->with('reason')
            ->orderBy('recorded_at')
            ->get();

        // Nach Grund gruppieren
        $groups = [];
        $totalEk = 0.0;
        $totalVk = 0.0;
        $totalQty = 0;

        foreach ($entries as $entry) {
            $reasonName = $entry->reason?->name ?? 'Ohne Grund';
            $groups[$reasonName] ??= [
                'name' => $reasonName,
                'items' => [],
                'sum_ek' => 0.0,
                'sum_vk' => 0.0,
                'qty' => 0,
            ];

            $lineEk = (float) ($entry->purchasing_price ?? 0) * $entry->quantity;
            $lineVk = (float) ($entry->selling_price ?? 0) * $entry->quantity;

            $groups[$reasonName]['items'][] = [
                'ean' => $entry->ean,
                'tms_no' => $entry->tms_no,
                'description' => $entry->article_description,
                'quantity' => $entry->quantity,
                'ek' => (float) ($entry->purchasing_price ?? 0),
                'vk' => (float) ($entry->selling_price ?? 0),
                'sum_ek' => $lineEk,
                'sum_vk' => $lineVk,
            ];
            $groups[$reasonName]['sum_ek'] += $lineEk;
            $groups[$reasonName]['sum_vk'] += $lineVk;
            $groups[$reasonName]['qty'] += $entry->quantity;

            $totalEk += $lineEk;
            $totalVk += $lineVk;
            $totalQty += $entry->quantity;
        }

        ksort($groups);

        $station = $stationId ? GasStation::find($stationId) : null;

        $title = 'Abschriften-Tagesbericht ' . $from->format('d.m.Y')
            . ($from->isSameDay($to) ? '' : ' – ' . $to->format('d.m.Y'));

        $pdf = Pdf::loadView('reports.depreciation', [
            'station' => $station,
            'stationName' => $station?->name ?? 'Alle Stationen',
            'from' => $from,
            'to' => $to,
            'groups' => array_values($groups),
            'totalEk' => $totalEk,
            'totalVk' => $totalVk,
            'totalQty' => $totalQty,
            'generatedAt' => now(),
        ])->setPaper('A4');

        // Datei speichern
        $directory = "reports/{$tenantId}";
        Storage::disk('local')->makeDirectory($directory);
        $filename = 'abschriften_' . $from->format('Y-m-d')
            . ($from->isSameDay($to) ? '' : '_' . $to->format('Y-m-d'))
            . '_' . substr(md5(uniqid('', true)), 0, 6) . '.pdf';
        $path = "{$directory}/{$filename}";
        Storage::disk('local')->put($path, $pdf->output());

        return GeneratedReport::create([
            'tenant_id' => $tenantId,
            'station_id' => $stationId,
            'user_id' => Auth::id(),
            'type' => 'depreciation',
            'title' => $title,
            'file_path' => $path,
            'file_size' => Storage::disk('local')->size($path),
            'period_from' => $from->toDateString(),
            'period_to' => $to->toDateString(),
            'meta' => [
                'count' => $entries->count(),
                'total_qty' => $totalQty,
                'total_ek' => round($totalEk, 2),
                'total_vk' => round($totalVk, 2),
                'reasons' => count($groups),
            ],
        ]);
    }
}
