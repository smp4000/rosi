<?php

namespace App\Services;

use App\Models\ArticleEan;
use App\Models\ArticleImport;
use App\Models\GasStation;
use App\Models\Mhd;
use Carbon\Carbon;

/**
 * CSV-Import: Lieferschein → MHD-Eintraege erstellen.
 *
 * Format: Semikolon-getrennt, Werte in Anfuehrungszeichen.
 * Spalten: Pos.;Bestellnr.;TMS Nr.;NAN;Artikelbeschreibung;Artikelgruppe;Menge;VPE;Preis;Gew.;Summe;Fehl-Menge;min MHD;Fehler
 *
 * Tankstellennummer wird aus dem Dateinamen extrahiert: LS_Tankstellennummer.csv
 */
class DeliveryNoteCsvImportService
{
    public function import(string $filePath, string $filename, ?string $userId): ArticleImport
    {
        $content = file_get_contents($filePath);
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $lines = explode("\n", $content);

        // Tankstellennummer aus Dateinamen: LS_Tankstellennummer.csv
        $stationNumber = $this->extractStationNumber($filename);
        $station = $this->findStation($stationNumber, $userId);

        if (! $station) {
            throw new \Exception("Tankstelle mit Nummer '{$stationNumber}' nicht gefunden.");
        }

        // Header-Zeile finden und Spalten-Indizes bestimmen
        $headerIndex = null;
        $columnMap = [];

        foreach ($lines as $i => $line) {
            $line = trim($line);
            if (str_contains($line, '"TMS Nr."') && str_contains($line, '"min MHD"')) {
                $headerIndex = $i;
                $columns = $this->parseCsvLine($line);
                foreach ($columns as $colIndex => $colName) {
                    $columnMap[trim($colName)] = $colIndex;
                }
                break;
            }
        }

        if ($headerIndex === null) {
            throw new \Exception('Header-Zeile nicht gefunden.');
        }

        $created = 0;
        $skippedDuplicate = 0;
        $skippedNoMhd = 0;
        $today = Carbon::today()->toDateString();

        for ($i = $headerIndex + 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) {
                continue;
            }

            $cols = $this->parseCsvLine($line);

            // Nur Artikel mit Menge >= 1 importieren
            $mengeRaw = trim($cols[$columnMap['Menge']] ?? '0');
            $menge = (float) str_replace(',', '.', $mengeRaw);
            if ($menge < 1) {
                continue;
            }

            $mhdRaw = trim($cols[$columnMap['min MHD']] ?? '');
            if (empty($mhdRaw)) {
                $skippedNoMhd++;
                continue;
            }

            // MHD-Datum parsen (Format: dd.mm.yyyy)
            try {
                $mhdDate = Carbon::createFromFormat('d.m.Y', $mhdRaw)->toDateString();
            } catch (\Exception $e) {
                $skippedNoMhd++;
                continue;
            }

            $tmsNo = (int) trim($cols[$columnMap['TMS Nr.']] ?? '0');
            $description = trim($cols[$columnMap['Artikelbeschreibung']] ?? '');

            if ($tmsNo === 0 || empty($description)) {
                continue;
            }

            // EAN aus article_eans per TMS-Nr. (= article_number) und Station nachschlagen
            $ean = ArticleEan::where('gas_station_id', $station->id)
                ->where('article_number', $tmsNo)
                ->value('ean');

            // Duplikat-Pruefung ueber Kenner
            $kenner = Mhd::generateKenner($station->id, $tmsNo, $mhdDate);
            $existing = Mhd::forStation($station->id)
                ->where('kenner', $kenner)
                ->first();

            if ($existing) {
                $skippedDuplicate++;
                continue;
            }

            Mhd::create([
                'station_id' => $station->id,
                'date_recorded' => $today,
                'recorded_by' => 'CSV-Import',
                'tms_no' => $tmsNo,
                'ean' => $ean ?: null,
                'article_description' => rtrim($description, ' >'),
                'mhd_date' => $mhdDate,
                'kenner' => $kenner,
                'supplier' => null,
                'delivery_date' => $today,
                'goods_disposed' => false,
            ]);

            $created++;
        }

        return ArticleImport::create([
            'gas_station_id' => $station->id,
            'station_number' => $station->station_number ?? $stationNumber,
            'csv_printed_at' => now(),
            'filename' => $filename,
            'status' => 'completed',
            'imported_by' => $userId,
            'articles_total' => $created + $skippedDuplicate + $skippedNoMhd,
            'articles_created' => $created,
            'articles_updated' => 0,
            'articles_unchanged' => $skippedDuplicate,
            'articles_not_found' => $skippedNoMhd,
        ]);
    }

    /**
     * Tankstellennummer aus Dateinamen extrahieren.
     * Format: LS_Tankstellennummer.csv oder LSNummer_Tankstellennummer.csv
     */
    private function extractStationNumber(string $filename): string
    {
        $basename = pathinfo($filename, PATHINFO_FILENAME);

        // Letzter Teil nach Underscore = Tankstellennummer
        $parts = explode('_', $basename);
        if (count($parts) >= 2) {
            return end($parts);
        }

        return $basename;
    }

    private function findStation(string $stationNumber, ?string $userId): ?GasStation
    {
        $normalizedNr = ltrim($stationNumber, '0');

        $query = GasStation::query();

        // Tenant-Kontext wenn vorhanden
        if ($userId) {
            $tenantId = \App\Models\User::find($userId)?->tenant_id;
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
        }

        return $query->where(function ($q) use ($normalizedNr) {
            $q->where('station_number', 'LIKE', '%' . $normalizedNr . '%')
              ->orWhere('station_number_shop', 'LIKE', '%' . $normalizedNr . '%')
              ->orWhere('station_number_fuel', 'LIKE', '%' . $normalizedNr . '%');
        })->first();
    }

    /**
     * CSV-Zeile parsen (Semikolon-getrennt, Anfuehrungszeichen).
     */
    private function parseCsvLine(string $line): array
    {
        $result = [];
        $fields = str_getcsv($line, ';', '"');
        foreach ($fields as $field) {
            $result[] = trim($field, '"');
        }
        return $result;
    }
}
