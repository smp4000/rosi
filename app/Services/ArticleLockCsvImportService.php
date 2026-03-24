<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleChange;
use App\Models\ArticleEan;
use App\Models\ArticleImport;
use App\Models\GasStation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service fuer den Import von Sperrlisten aus CSV-Dateien.
 *
 * Erkennung: CSV-Header enthaelt 'Artikel mit Kenner "Sperren"'
 * Aktion: Artikel werden als gesperrt markiert (is_locked = true)
 * Gesperrte Artikel koennen nicht ausgewaehlt/verwendet werden.
 *
 * Unterstuetzt beide CSV-Formate:
 * - Artikelstammdaten (ArtDat*.CSV)
 * - Artikelstammdaten Mengenverordnung (ArtDatEx*.CSV)
 */
class ArticleLockCsvImportService
{
    use \App\Traits\ValidatesCsvRows;

    private ?string $stationNumber = null;
    private ?Carbon $csvPrintedAt = null;
    private ?GasStation $gasStation = null;

    // Gesammelte Artikelnummern (dedupliziert)
    private array $articleNumbers = [];

    // Statistik
    private int $locked = 0;
    private int $notFound = 0;
    private int $alreadyLocked = 0;

    /**
     * Sperrliste importieren: Artikel als gesperrt markieren.
     *
     * @param string $filePath Pfad zur CSV-Datei
     * @param string $originalFilename Original-Dateiname
     * @param string|null $userId User-ID des Importierenden
     * @return ArticleImport Import-Protokoll
     * @throws \Exception Bei Fehlern
     */
    public function import(string $filePath, string $originalFilename, ?string $userId = null): ArticleImport
    {
        // 1. CSV einlesen
        $lines = $this->readCsvFile($filePath);

        if (! $this->validateCsvNotEmpty(implode("\n", $lines), 5)) {
            throw new \Exception('CSV-Datei ist leer oder enthaelt zu wenige Zeilen.');
        }

        // 2. Metadaten extrahieren
        $this->extractMetadata($lines);

        if (! $this->stationNumber) {
            throw new \Exception('Stationsnummer konnte nicht aus der CSV-Datei extrahiert werden.');
        }

        // 3. Tankstelle finden
        $normalizedNr = ltrim($this->stationNumber, '0');
        $this->gasStation = GasStation::where(function ($q) use ($normalizedNr) {
            $q->where('station_number', 'LIKE', '%' . $normalizedNr . '%')
              ->orWhere('station_number_shop', 'LIKE', '%' . $normalizedNr . '%')
              ->orWhere('station_number_fuel', 'LIKE', '%' . $normalizedNr . '%');
        })->first();

        if (! $this->gasStation) {
            throw new \Exception("Keine Tankstelle mit Stationsnummer '{$this->stationNumber}' (normalisiert: {$normalizedNr}) gefunden.");
        }

        // 4. Import-Protokoll erstellen
        $import = ArticleImport::create([
            'gas_station_id' => $this->gasStation->id,
            'station_number' => $this->stationNumber,
            'csv_printed_at' => $this->csvPrintedAt ?? now(),
            'filename' => $originalFilename,
            'status' => 'processing',
            'imported_by' => $userId,
        ]);

        try {
            // 5. Artikelnummern extrahieren
            $this->parseArticleNumbers($lines);

            if (empty($this->articleNumbers)) {
                throw new \Exception('Keine Artikelnummern in der Sperrliste gefunden.');
            }

            // 6. Artikel sperren
            DB::transaction(function () use ($import) {
                $this->lockArticles($import);
            });

            // 7. Import abschliessen
            $import->update([
                'status' => 'completed',
                'articles_total' => count($this->articleNumbers),
                'articles_locked' => $this->locked,
                'articles_not_found' => $this->notFound,
                'articles_unchanged' => $this->alreadyLocked,
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

    /**
     * CSV-Datei einlesen (Encoding-sicher).
     */
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

    /**
     * Stationsnummer + Druckdatum extrahieren.
     */
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
                    // ignorieren
                }
            }

            if ($this->stationNumber && $this->csvPrintedAt) {
                break;
            }
        }
    }

    /**
     * Artikelnummern aus der CSV extrahieren.
     */
    private function parseArticleNumbers(array $lines): void
    {
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            if ($this->isSkipLine($line)) {
                continue;
            }

            $cols = str_getcsv($line);
            if (empty($cols) || ! isset($cols[0])) {
                continue;
            }

            $firstCol = trim($cols[0]);

            if (! is_numeric($firstCol)) {
                continue;
            }

            $nr = (int) $firstCol;

            // Gruppen-Zeilen ueberspringen (keine Einheit)
            $hasUnit = false;
            foreach ([2, 4] as $unitCol) {
                if (isset($cols[$unitCol]) && in_array(trim($cols[$unitCol]), ['St', 'l', 'kg', 'Pkg', 'Paar'])) {
                    $hasUnit = true;
                    break;
                }
            }

            if (! $hasUnit) {
                continue;
            }

            if (! in_array($nr, $this->articleNumbers)) {
                $this->articleNumbers[] = $nr;
            }
        }
    }

    /**
     * Zeile ueberspringen?
     */
    private function isSkipLine(string $line): bool
    {
        $skipPatterns = [
            'Aral STATION',
            'Schlitzer Str',
            'Liste:',
            'Selektion:',
            'Nr.',
            'Modus:',
            'Stationsnummer:',
            'Tel:',
            'Fax:',
        ];

        foreach ($skipPatterns as $pattern) {
            if (str_contains($line, $pattern)) {
                return true;
            }
        }

        if (preg_match('/^\d{5}\s+\w+/', $line)) {
            return true;
        }

        return false;
    }

    /**
     * Artikel als gesperrt markieren und EANs ebenfalls sperren.
     */
    private function lockArticles(ArticleImport $import): void
    {
        foreach ($this->articleNumbers as $articleNumber) {
            $article = Article::where('gas_station_id', $this->gasStation->id)
                ->where('article_number', $articleNumber)
                ->first();

            if (! $article) {
                $this->notFound++;
                Log::info("Sperrliste: Artikel {$articleNumber} nicht in DB gefunden (Station: {$this->gasStation->name})");
                continue;
            }

            if ($article->is_locked) {
                $this->alreadyLocked++;
                continue;
            }

            // Artikel sperren + Status auf 'locked' setzen
            $oldStatus = $article->status;
            $article->update([
                'is_locked' => true,
                'locked_at' => now(),
                'status' => 'locked',
            ]);

            // Zugehoerige EANs ebenfalls sperren
            ArticleEan::where('gas_station_id', $this->gasStation->id)
                ->where('article_number', $articleNumber)
                ->where('is_locked', false)
                ->update(['is_locked' => true]);

            // Change loggen
            ArticleChange::create([
                'article_id' => $article->id,
                'article_import_id' => $import->id,
                'change_type' => 'locked',
                'field_name' => 'status',
                'old_value' => $oldStatus,
                'new_value' => 'locked',
            ]);

            $this->locked++;
        }
    }

    /**
     * Nur Metadaten extrahieren (ohne Import).
     */
    public function extractInfo(string $filePath): array
    {
        $lines = $this->readCsvFile($filePath);
        $this->extractMetadata($lines);

        return [
            'station_number' => $this->stationNumber,
            'csv_printed_at' => $this->csvPrintedAt,
            'line_count' => count($lines),
        ];
    }
}
