<?php

namespace App\Services;

use App\Models\Article;
use App\Models\ArticleChange;
use App\Models\ArticleGroup;
use App\Models\ArticleImport;
use App\Models\GasStation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service fuer den Import von Artikelstammdaten aus CSV-Dateien.
 *
 * CSV-Format: TMS Artikelstammdaten-Export
 * - Seitenumbrueche mit Header-Wiederholung
 * - Geschaeftsbereich-Zeilen (z.B. "3,,KFZ-SERVICE")
 * - Artikelgruppen-Zeilen (z.B. "160,,Zubehoer")
 * - Artikelzeilen mit deutschem Zahlenformat
 * - Duplikate: Gleiche Artikelnr → hoechster VK mit Stueckzahl 1
 */
class ArticleCsvImportService
{
    private ?string $stationNumber = null;
    private ?Carbon $csvPrintedAt = null;
    private ?GasStation $gasStation = null;

    // State-Machine fuer Parser
    private ?int $currentBusinessAreaId = null;
    private ?int $currentArticleGroupId = null;
    private ?string $currentArticleGroupDescription = null;

    // Gesammelte Artikel (Key: article_number)
    private array $parsedArticles = [];

    // Statistik
    private int $created = 0;
    private int $updated = 0;
    private int $unchanged = 0;

    /**
     * CSV-Datei importieren.
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

        // 2. Metadaten extrahieren (Stationsnummer + Druckdatum)
        $this->extractMetadata($lines);

        if (! $this->stationNumber) {
            throw new \Exception('Stationsnummer konnte nicht aus der CSV-Datei extrahiert werden.');
        }

        // 3. Tankstelle anhand Stationsnummer finden (fuehrende Nullen entfernen, alle Felder pruefen)
        $normalizedNr = ltrim($this->stationNumber, '0');
        $this->gasStation = GasStation::where(function ($q) use ($normalizedNr) {
            $q->where('station_number', 'LIKE', '%' . $normalizedNr . '%')
              ->orWhere('station_number_shop', 'LIKE', '%' . $normalizedNr . '%')
              ->orWhere('station_number_fuel', 'LIKE', '%' . $normalizedNr . '%');
        })->first();

        if (! $this->gasStation) {
            throw new \Exception("Keine Tankstelle mit Stationsnummer '{$this->stationNumber}' (normalisiert: {$normalizedNr}) gefunden. Bitte zuerst die Stationsnummer in den Tankstellen-Stammdaten hinterlegen.");
        }

        // 4. Druckdatum pruefen (aelter als letzter Import?)
        $lastImport = ArticleImport::where('gas_station_id', $this->gasStation->id)
            ->where('status', 'completed')
            ->orderByDesc('csv_printed_at')
            ->first();

        if ($lastImport && $this->csvPrintedAt && $this->csvPrintedAt->lt($lastImport->csv_printed_at)) {
            throw new \Exception(
                "Die CSV-Datei (Druckdatum: {$this->csvPrintedAt->format('d.m.Y H:i')}) ist älter als der letzte Import " .
                "(Druckdatum: {$lastImport->csv_printed_at->format('d.m.Y H:i')}). " .
                "Bitte eine neuere Datei verwenden."
            );
        }

        // 5. Import-Protokoll erstellen
        $import = ArticleImport::create([
            'gas_station_id' => $this->gasStation->id,
            'station_number' => $this->stationNumber,
            'csv_printed_at' => $this->csvPrintedAt ?? now(),
            'filename' => $originalFilename,
            'status' => 'processing',
            'imported_by' => $userId,
        ]);

        try {
            // 6. Zeilen parsen
            $this->parseLines($lines);

            // 7. Duplikate aufloesen
            $this->resolveDuplicates();

            // 8. In DB speichern
            DB::transaction(function () use ($import) {
                $this->saveArticles($import);
            });

            // 9. Import abschliessen
            $import->update([
                'status' => 'completed',
                'articles_total' => count($this->parsedArticles),
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

        return explode("\n", $content);
    }

    /**
     * Stationsnummer + Druckdatum aus den Footer-Zeilen extrahieren.
     */
    private function extractMetadata(array $lines): void
    {
        foreach ($lines as $line) {
            // "Stationsnummer: 0170320196,,,,,,,,,Druckdatum: 05.11.2025 08:30"
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

            // Sobald beides gefunden, abbrechen
            if ($this->stationNumber && $this->csvPrintedAt) {
                break;
            }
        }
    }

    /**
     * Zeilen parsen mit State-Machine.
     * Erkennt: Header, Seitenumbrueche, Geschaeftsbereiche, Artikelgruppen, Artikel.
     */
    private function parseLines(array $lines): void
    {
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Seitenumbruch / Header erkennen → ueberspringen
            if ($this->isHeaderOrPageBreak($line)) {
                continue;
            }

            $cols = str_getcsv($line);
            if (empty($cols) || ! isset($cols[0])) {
                continue;
            }

            $firstCol = trim($cols[0]);

            // Leere erste Spalte = Modus-Legende oder Stationsnummer-Zeile
            if ($firstCol === '' || $firstCol === 'Nr.') {
                continue;
            }

            // Erste Spalte muss numerisch sein
            if (! is_numeric($firstCol)) {
                // Koennte "Stationsnummer:" oder "Aral STATION" sein → skip
                continue;
            }

            $nr = (int) $firstCol;

            // Unterscheidung: Gruppe vs. Artikel
            // Gruppen haben nur Nr + Bezeichnung (Spalte 2), Rest leer
            // Artikel haben Einheit (Spalte 3), EK (Spalte 5/6), VK (Spalte 7/8)
            if ($this->isGroupLine($cols)) {
                $this->handleGroupLine($nr, $cols);
            } else {
                $this->handleArticleLine($nr, $cols);
            }
        }
    }

    /**
     * Pruefen ob Zeile ein Header oder Seitenumbruch ist.
     */
    private function isHeaderOrPageBreak(string $line): bool
    {
        $skipPatterns = [
            'Aral STATION',
            'Schlitzer Str',
            'Liste:',
            'Selektion:',
            'Nr.,',
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

        // PLZ-Zeile (z.B. "36039 Fulda")
        if (preg_match('/^\d{5}\s+\w+,/', $line)) {
            return true;
        }

        return false;
    }

    /**
     * Pruefen ob es eine Gruppen-Zeile ist (nur Nr + Bezeichnung, Rest leer).
     *
     * CSV-Spalten (mit str_getcsv, Komma-Separator):
     * 0=Nr, 1=leer, 2=Bezeichnung, 3=leer, 4=Einheit, 5=leer, 6=EK, 7=leer, 8=VK
     */
    private function isGroupLine(array $cols): bool
    {
        // Einheit steht in Spalte 4 (nicht 3!)
        $unit = isset($cols[4]) ? trim($cols[4]) : '';

        // Wenn keine Einheit → wahrscheinlich Gruppe
        if (empty($unit)) {
            return true;
        }

        return false;
    }

    /**
     * Gruppen-Zeile verarbeiten.
     * Geschaeftsbereich oder Artikelgruppe erkennen.
     */
    private function handleGroupLine(int $nr, array $cols): void
    {
        $name = isset($cols[2]) ? trim($cols[2]) : '';

        // Geschaeftsbereich erkennen (ID < 10, Name in Grossbuchstaben)
        if ($nr < 10 && mb_strtoupper($name) === $name && ! empty($name)) {
            $this->currentBusinessAreaId = $nr;
            return;
        }

        // Artikelgruppe: In article_groups nachschlagen
        // CSV zeigt verschiedene Ebenen - suche in allen ID-Feldern
        $group = ArticleGroup::where('article_group_04_id', $nr)
            ->orWhere('article_group_03_id', $nr)
            ->orWhere('article_group_02_01_id', $nr)
            ->orWhere('product_group_id', $nr)
            ->first();

        if ($group) {
            $this->currentArticleGroupId = $group->id;
            $this->currentArticleGroupDescription = $name ?: $group->article_group_04;
        } else {
            // Nicht gefunden in DB - trotzdem Beschreibung aus CSV merken
            $this->currentArticleGroupDescription = $name ?: $this->currentArticleGroupDescription;
            Log::info("CSV-Import: Unbekannte Artikelgruppe {$nr} ({$name}) - behalte vorherige Zuordnung bei");
        }
    }

    /**
     * Artikel-Zeile verarbeiten.
     *
     * CSV-Spalten (str_getcsv, 0-basiert):
     *  0: Nr (Artikelnummer)
     *  2: Bezeichnung
     *  4: Einheit (St, l, kg)
     *  6: akt.EK/EUR (deutsch: "1,865")
     *  8: akt.VK/EUR (deutsch: "5,59")
     * 12: EAN-Code
     * 15: Mengenfaktor
     * 17: H.Lief. (Hauptlieferant-Nr)
     * 18: Bestellnr.
     * 21: Modus
     * 22: (leer)
     * 25: Mindest Bestand (oder 23 je nach Zeile)
     * 26: Höchst Bestand / Istbestand
     */
    private function handleArticleLine(int $nr, array $cols): void
    {
        $name = isset($cols[2]) ? trim($cols[2]) : '';
        $unit = isset($cols[4]) ? trim($cols[4]) : null;
        $ek = $this->parseGermanDecimal($cols[6] ?? '');
        $vk = $this->parseGermanDecimal($cols[8] ?? '');
        $quantityFactor = isset($cols[15]) ? trim($cols[15]) : null;
        $supplierNr = isset($cols[17]) ? trim($cols[17]) : null;
        $orderNr = isset($cols[18]) ? trim($cols[18]) : null;
        $modus = isset($cols[21]) ? (int) trim($cols[21]) : 0;
        $minStock = (int) ($this->parseGermanDecimal($cols[22] ?? '') ?? 0);
        $maxStock = (int) ($this->parseGermanDecimal($cols[25] ?? '') ?? 0);
        $currentStock = (int) ($this->parseGermanDecimal($cols[26] ?? '') ?? 0);

        $article = [
            'article_number' => $nr,
            'name' => $name,
            'unit' => $unit,
            'purchasing_price' => $ek,
            'selling_price' => $vk,
            'quantity_factor' => $quantityFactor ?: null,
            'supplier_number' => is_numeric($supplierNr) ? (int) $supplierNr : null,
            'order_number' => $orderNr ?: null,
            'modus' => $modus,
            'min_stock' => $minStock,
            'max_stock' => $maxStock,
            'current_stock' => $currentStock,
            'article_group_id' => $this->currentArticleGroupId,
            'article_group_description' => $this->currentArticleGroupDescription,
        ];

        $key = $nr;

        if (! isset($this->parsedArticles[$key])) {
            // Erster Eintrag fuer diese Artikelnr
            $this->parsedArticles[$key] = $article;
        } else {
            // Duplikat: Hoechsten VK mit Mengenfaktor 1 bevorzugen
            $existing = $this->parsedArticles[$key];
            $existingFactor = $this->parseGermanDecimal($existing['quantity_factor'] ?? '1') ?: 1;
            $newFactor = $this->parseGermanDecimal($quantityFactor ?? '1') ?: 1;

            // Regel: Stueckzahl 1 bevorzugen, dann hoechster VK
            if ($newFactor == 1 && $existingFactor != 1) {
                $this->parsedArticles[$key] = $article;
            } elseif ($newFactor == 1 && $existingFactor == 1 && $vk > $existing['selling_price']) {
                $this->parsedArticles[$key] = $article;
            }
        }
    }

    /**
     * Deutsches Dezimalformat parsen ("1.234,56" → 1234.56).
     */
    private function parseGermanDecimal(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        // Anführungszeichen entfernen
        $value = trim($value, '"');
        // Tausenderpunkt entfernen, Komma → Punkt
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Duplikate aufloesen (bereits in handleArticleLine erledigt).
     */
    private function resolveDuplicates(): void
    {
        // Bereits in handleArticleLine aufgeloest
    }

    /**
     * Geparste Artikel in die DB speichern.
     */
    private function saveArticles(ArticleImport $import): void
    {
        // Bestehende Artikel dieser Station laden
        $existingArticles = Article::where('gas_station_id', $this->gasStation->id)
            ->get()
            ->keyBy('article_number');

        foreach ($this->parsedArticles as $articleNumber => $data) {
            $existing = $existingArticles->get($articleNumber);

            if (! $existing) {
                // Neuer Artikel
                $article = Article::create(array_merge($data, [
                    'gas_station_id' => $this->gasStation->id,
                    'status' => 'new',
                    'csv_printed_at' => $this->csvPrintedAt,
                    'imported_at' => now(),
                ]));

                ArticleChange::create([
                    'article_id' => $article->id,
                    'article_import_id' => $import->id,
                    'change_type' => 'created',
                ]);

                $this->created++;
            } else {
                // Bestehender Artikel: Preisaenderungen pruefen
                $changes = [];

                if (bccomp((string) $existing->selling_price, (string) $data['selling_price'], 2) !== 0) {
                    $changes['selling_price'] = [
                        'old' => $existing->selling_price,
                        'new' => $data['selling_price'],
                    ];
                }

                if (bccomp((string) $existing->purchasing_price, (string) $data['purchasing_price'], 3) !== 0) {
                    $changes['purchasing_price'] = [
                        'old' => $existing->purchasing_price,
                        'new' => $data['purchasing_price'],
                    ];
                }

                // Weitere Felder pruefen
                $checkFields = ['name', 'unit', 'supplier_number', 'order_number', 'min_stock', 'max_stock'];
                foreach ($checkFields as $field) {
                    if (($existing->$field ?? '') != ($data[$field] ?? '')) {
                        $changes[$field] = [
                            'old' => $existing->$field,
                            'new' => $data[$field],
                        ];
                    }
                }

                if (! empty($changes)) {
                    // Update
                    $existing->update(array_merge($data, [
                        'status' => 'price_changed',
                        'csv_printed_at' => $this->csvPrintedAt,
                        'imported_at' => now(),
                    ]));

                    foreach ($changes as $field => $vals) {
                        ArticleChange::create([
                            'article_id' => $existing->id,
                            'article_import_id' => $import->id,
                            'change_type' => in_array($field, ['selling_price', 'purchasing_price']) ? 'price_updated' : 'data_updated',
                            'field_name' => $field,
                            'old_value' => (string) $vals['old'],
                            'new_value' => (string) $vals['new'],
                        ]);
                    }

                    $this->updated++;
                } else {
                    // Keine Aenderung, aber Status auf active setzen
                    if ($existing->status !== 'active') {
                        $existing->update(['status' => 'active']);
                    }
                    $this->unchanged++;
                }
            }
        }
    }

    /**
     * Getter fuer extrahierte Metadaten (zum Anzeigen vor dem Import).
     */
    public function getStationNumber(): ?string
    {
        return $this->stationNumber;
    }

    public function getCsvPrintedAt(): ?Carbon
    {
        return $this->csvPrintedAt;
    }

    /**
     * Nur Metadaten extrahieren (ohne Import).
     * Nuetzlich fuer Vorschau/Validierung.
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
