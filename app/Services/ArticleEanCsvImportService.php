<?php

namespace App\Services;

use App\Models\ArticleEan;
use App\Models\ArticleImport;
use App\Models\GasStation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service fuer den Import von Artikel-EAN-Daten aus CSV-Dateien.
 *
 * CSV-Format: TMS Artikelstammdaten (Mengenverordnung) Export
 * Spalten (str_getcsv):
 *  0: Nr (Artikelnummer)
 *  1: Bezeichnung
 *  2: Einheit
 *  4: akt.VK/EUR
 *  7: Basiseinheit
 * 10: Basismenge
 * 12: Referenzmenge
 * 13: EAN-Code
 * 16: Mengenfaktor
 * 17: Verkaufsgebinde
 * 21: Preis an EAN
 */
class ArticleEanCsvImportService
{
    use \App\Traits\ValidatesCsvRows;
    private ?string $stationNumber = null;
    private ?Carbon $csvPrintedAt = null;
    private ?GasStation $gasStation = null;

    // Gesammelte EANs (Key: "ean")
    private array $parsedEans = [];

    // Statistik
    private int $created = 0;
    private int $updated = 0;
    private int $unchanged = 0;

    /**
     * CSV-Datei importieren.
     */
    public function import(string $filePath, string $originalFilename, ?string $userId = null): ArticleImport
    {
        $lines = $this->readCsvFile($filePath);

        if (! $this->validateCsvNotEmpty(implode("\n", $lines), 5)) {
            throw new \Exception('CSV-Datei ist leer oder enthaelt zu wenige Zeilen.');
        }

        $this->extractMetadata($lines);

        if (! $this->stationNumber) {
            throw new \Exception('Stationsnummer konnte nicht aus der CSV-Datei extrahiert werden.');
        }

        // Tankstelle finden (alle Stationsnummer-Felder pruefen)
        $normalizedNr = ltrim($this->stationNumber, '0');
        $this->gasStation = GasStation::where(function ($q) use ($normalizedNr) {
            $q->where('station_number', 'LIKE', '%' . $normalizedNr . '%')
              ->orWhere('station_number_shop', 'LIKE', '%' . $normalizedNr . '%')
              ->orWhere('station_number_fuel', 'LIKE', '%' . $normalizedNr . '%');
        })->first();

        if (! $this->gasStation) {
            throw new \Exception("Keine Tankstelle mit Stationsnummer '{$this->stationNumber}' gefunden.");
        }

        // Import-Protokoll
        $import = ArticleImport::create([
            'gas_station_id' => $this->gasStation->id,
            'station_number' => $this->stationNumber,
            'csv_printed_at' => $this->csvPrintedAt ?? now(),
            'filename' => $originalFilename,
            'status' => 'processing',
            'imported_by' => $userId,
        ]);

        try {
            $this->parseLines($lines);

            DB::transaction(function () use ($import) {
                $this->saveEans($import);
            });

            $import->update([
                'status' => 'completed',
                'articles_total' => count($this->parsedEans),
                'articles_created' => $this->created,
                'articles_updated' => $this->updated,
                'articles_unchanged' => $this->unchanged,
            ]);
        } catch (\Exception $e) {
            $import->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $import;
    }

    private function readCsvFile(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \Exception("CSV-Datei konnte nicht gelesen werden: {$filePath}");
        }
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        return explode("\n", $content);
    }

    private function extractMetadata(array $lines): void
    {
        foreach ($lines as $line) {
            if (preg_match('/Stationsnummer:\s*(\d+)/', $line, $m)) {
                $this->stationNumber = $m[1];
            }
            if (preg_match('/Druckdatum:\s*(\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2})/', $line, $m)) {
                try {
                    $this->csvPrintedAt = Carbon::createFromFormat('d.m.Y H:i', $m[1]);
                } catch (\Exception $e) {
                }
            }
            if ($this->stationNumber && $this->csvPrintedAt) {
                break;
            }
        }
    }

    private function parseLines(array $lines): void
    {
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if ($this->isHeaderOrPageBreak($line)) {
                continue;
            }

            $cols = str_getcsv($line);
            $firstCol = trim($cols[0] ?? '');

            if (! is_numeric($firstCol)) {
                continue;
            }

            $nr = (int) $firstCol;

            // Gruppen-Zeile: keine Einheit in Spalte 2
            $unit = isset($cols[2]) ? trim($cols[2]) : '';
            if (empty($unit)) {
                continue; // Geschaeftsbereich oder Artikelgruppe, ueberspringen
            }

            // EAN-Code in Spalte 13
            $ean = isset($cols[13]) ? trim($cols[13]) : '';
            if (empty($ean)) {
                continue; // Kein EAN, ueberspringen
            }

            $this->handleEanLine($nr, $cols, $ean);
        }
    }

    private function isHeaderOrPageBreak(string $line): bool
    {
        $skipPatterns = [
            'Aral STATION', 'Schlitzer Str', 'Liste:', 'Selektion:',
            'Nr.,', 'Stationsnummer:', 'Tel:', 'Fax:',
        ];
        foreach ($skipPatterns as $pattern) {
            if (str_contains($line, $pattern)) {
                return true;
            }
        }
        if (preg_match('/^\d{5}\s+\w+,/', $line)) {
            return true;
        }
        return false;
    }

    /**
     * EAN-Zeile verarbeiten.
     *
     * Spalten (str_getcsv):
     *  0: Nr (Artikelnummer)
     *  1: Bezeichnung
     *  2: Einheit
     *  4: akt.VK/EUR
     *  7: Basiseinheit
     * 10: Basismenge
     * 12: Referenzmenge
     * 13: EAN-Code
     * 16: Mengenfaktor
     * 17: Verkaufsgebinde
     * 21: Preis an EAN
     */
    private function handleEanLine(int $articleNumber, array $cols, string $ean): void
    {
        $vkEan = $this->parseGermanDecimal($cols[21] ?? '');
        $baseQty = $this->parseGermanDecimal($cols[10] ?? '');
        $refQty = $this->parseGermanDecimal($cols[12] ?? '');
        $baseUnit = isset($cols[7]) ? trim($cols[7]) : null;
        $factor = isset($cols[16]) ? trim($cols[16]) : null;
        $packaging = isset($cols[17]) ? trim($cols[17]) : null;

        // Key: EAN (pro Tankstelle unique)
        $this->parsedEans[$ean] = [
            'article_number' => $articleNumber,
            'ean' => $ean,
            'selling_price' => $vkEan ?? 0.00,
            'base_quantity' => $baseQty,
            'reference_quantity' => $refQty,
            'base_unit' => $baseUnit ?: null,
            'quantity_factor' => $factor ?: null,
            'sales_packaging' => $packaging ?: null,
        ];
    }

    private function parseGermanDecimal(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim(trim($value), '"');
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
        return is_numeric($value) ? (float) $value : null;
    }

    private function saveEans(ArticleImport $import): void
    {
        $existingEans = ArticleEan::where('gas_station_id', $this->gasStation->id)
            ->get()
            ->keyBy('ean');

        foreach ($this->parsedEans as $ean => $data) {
            $existing = $existingEans->get($ean);

            if (! $existing) {
                ArticleEan::updateOrCreate(
                    ['ean' => $data['ean'], 'gas_station_id' => $this->gasStation->id],
                    array_merge($data, [
                        'csv_printed_at' => $this->csvPrintedAt,
                        'imported_at' => now(),
                    ])
                );
                $this->created++;
            } else {
                // Pruefen ob sich was geaendert hat
                $changed = false;
                $checkFields = ['article_number', 'selling_price', 'base_quantity', 'reference_quantity', 'quantity_factor', 'sales_packaging'];
                foreach ($checkFields as $field) {
                    if (($existing->$field ?? '') != ($data[$field] ?? '')) {
                        $changed = true;
                        break;
                    }
                }

                if ($changed) {
                    $existing->update(array_merge($data, [
                        'csv_printed_at' => $this->csvPrintedAt,
                        'imported_at' => now(),
                    ]));
                    $this->updated++;
                } else {
                    $this->unchanged++;
                }
            }
        }
    }
}
