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
 * Service fuer den Import von Loeschlisten aus CSV-Dateien.
 *
 * Erkennung: CSV-Header enthaelt 'Artikel mit Kenner "Löschen"'
 * Aktion: Soft-Delete der enthaltenen Artikel (status → deleted, deleted_at gesetzt)
 *
 * Unterstuetzt beide CSV-Formate:
 * - Artikelstammdaten (ArtDat*.CSV)
 * - Artikelstammdaten Mengenverordnung (ArtDatEx*.CSV)
 */
class ArticleDeleteCsvImportService
{
    use \App\Traits\ValidatesCsvRows;

    private ?string $stationNumber = null;
    private ?Carbon $csvPrintedAt = null;
    private ?GasStation $gasStation = null;

    // Gesammelte Artikelnummern (dedupliziert)
    private array $articleNumbers = [];

    // Statistik
    private int $deleted = 0;
    private int $notFound = 0;
    private int $alreadyDeleted = 0;

    /**
     * Loeschliste importieren: Artikel soft-deleten.
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

        // 2. Metadaten extrahieren (Stationsnummer + Druckdatum)
        $this->extractMetadata($lines);

        if (! $this->stationNumber) {
            throw new \Exception('Stationsnummer konnte nicht aus der CSV-Datei extrahiert werden.');
        }

        // 3. Tankstelle anhand Stationsnummer finden
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
            // 5. Artikelnummern aus CSV extrahieren
            $this->parseArticleNumbers($lines);

            if (empty($this->articleNumbers)) {
                throw new \Exception('Keine Artikelnummern in der Löschliste gefunden.');
            }

            // 6. Artikel soft-deleten
            DB::transaction(function () use ($import) {
                $this->softDeleteArticles($import);
            });

            // 7. Import abschliessen
            $import->update([
                'status' => 'completed',
                'articles_total' => count($this->articleNumbers),
                'articles_deleted' => $this->deleted,
                'articles_not_found' => $this->notFound,
                'articles_unchanged' => $this->alreadyDeleted,
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

        // ANSI/Windows-1252 → UTF-8 konvertieren
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        // Non-Breaking Spaces durch normale Leerzeichen ersetzen (CSV-Artefakt)
        $content = str_replace("\xC2\xA0", ' ', $content);

        return explode("\n", $content);
    }

    /**
     * Stationsnummer + Druckdatum aus den Footer-Zeilen extrahieren.
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
                    // Druckdatum nicht parsebar, ignorieren
                }
            }

            if ($this->stationNumber && $this->csvPrintedAt) {
                break;
            }
        }
    }

    /**
     * Artikelnummern aus der CSV extrahieren.
     * Erkennt beide Formate (Artikelstammdaten + Mengenverordnung).
     */
    private function parseArticleNumbers(array $lines): void
    {
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Header/Meta-Zeilen ueberspringen
            if ($this->isSkipLine($line)) {
                continue;
            }

            $cols = str_getcsv($line);
            if (empty($cols) || ! isset($cols[0])) {
                continue;
            }

            $firstCol = trim($cols[0]);

            // Muss numerisch sein
            if (! is_numeric($firstCol)) {
                continue;
            }

            $nr = (int) $firstCol;

            // Gruppen-Zeilen ueberspringen (keine Einheit)
            // Format 1 (Artikelstammdaten): Einheit in Spalte 4
            // Format 2 (Mengenverordnung): Einheit in Spalte 2
            $hasUnit = false;

            // Pruefe beide moegliche Positionen fuer Einheit
            foreach ([2, 4] as $unitCol) {
                if (isset($cols[$unitCol]) && in_array(trim($cols[$unitCol]), ['St', 'l', 'kg', 'Pkg', 'Paar'])) {
                    $hasUnit = true;
                    break;
                }
            }

            if (! $hasUnit) {
                // Geschaeftsbereich oder Artikelgruppe → ueberspringen
                continue;
            }

            // Artikelnummer merken (dedupliziert)
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

        // PLZ-Zeile
        if (preg_match('/^\d{5}\s+\w+/', $line)) {
            return true;
        }

        return false;
    }

    /**
     * Artikel soft-deleten und Changes loggen.
     */
    private function softDeleteArticles(ArticleImport $import): void
    {
        foreach ($this->articleNumbers as $articleNumber) {
            $article = Article::where('gas_station_id', $this->gasStation->id)
                ->where('article_number', $articleNumber)
                ->first();

            if (! $article) {
                // Artikel existiert nicht in DB
                $this->notFound++;
                Log::info("Löschliste: Artikel {$articleNumber} nicht in DB gefunden (Station: {$this->gasStation->name})");
                continue;
            }

            if ($article->trashed()) {
                // Bereits soft-deleted
                $this->alreadyDeleted++;
                continue;
            }

            // Status auf 'deleted' setzen + Soft-Delete ausfuehren
            $oldStatus = $article->status;
            $article->update(['status' => 'deleted']);
            $article->delete(); // SoftDelete → setzt deleted_at

            // Zugehoerige EANs ebenfalls soft-deleten
            ArticleEan::where('gas_station_id', $this->gasStation->id)
                ->where('article_number', $articleNumber)
                ->whereNull('deleted_at')
                ->each(function (ArticleEan $ean) {
                    $ean->delete();
                });

            // Change loggen
            ArticleChange::create([
                'article_id' => $article->id,
                'article_import_id' => $import->id,
                'change_type' => 'soft_deleted',
                'field_name' => 'status',
                'old_value' => $oldStatus,
                'new_value' => 'deleted',
            ]);

            $this->deleted++;
        }
    }

    /**
     * Pruefen ob eine CSV-Datei eine Loeschliste ist.
     * Sucht nach dem Pattern 'Artikel mit Kenner "Löschen"' im Header.
     */
    public static function isDeleteList(string $content): bool
    {
        // Erste 10 Zeilen durchsuchen
        $lines = array_slice(explode("\n", $content), 0, 10);
        $headerText = implode("\n", $lines);

        return str_contains($headerText, 'Kenner') && str_contains($headerText, 'schen');
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
