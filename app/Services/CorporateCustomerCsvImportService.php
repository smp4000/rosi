<?php

namespace App\Services;

use App\Models\ArticleImport;
use App\Models\CorporateCustomer;
use App\Models\GasStation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service fuer den Import von Firmenkunden aus CSV-Dateien (InvcAddrList).
 *
 * CSV-Format: Multi-line Adressblock pro Kunde.
 * Record-Grenze: Zeile mit "Firmennummer:" markiert Beginn eines neuen Kunden.
 * Erkennung: CSV-Header enthaelt "Firmenliste".
 */
class CorporateCustomerCsvImportService
{
    use \App\Traits\ValidatesCsvRows;

    private ?string $stationNumber = null;
    private ?Carbon $csvPrintedAt = null;
    private ?GasStation $gasStation = null;

    // Gesammelte Kunden (Key: customer_number)
    private array $parsedCustomers = [];

    // Aktueller Kunden-Block waehrend Parsing
    private array $currentBlock = [];
    private ?int $currentCustomerNumber = null;

    // Statistik
    private int $created = 0;
    private int $updated = 0;
    private int $unchanged = 0;

    /**
     * Firmenliste importieren.
     */
    public function import(string $filePath, string $originalFilename, ?string $userId = null): ArticleImport
    {
        $lines = $this->readCsvFile($filePath);

        if (! $this->validateCsvNotEmpty(implode("\n", $lines), 3)) {
            throw new \Exception('CSV-Datei ist leer oder enthaelt zu wenige Zeilen.');
        }

        $this->extractMetadata($lines);

        if (! $this->stationNumber) {
            throw new \Exception('Stationsnummer konnte nicht aus der CSV-Datei extrahiert werden.');
        }

        $normalizedNr = ltrim($this->stationNumber, '0');
        $this->gasStation = GasStation::where(function ($q) use ($normalizedNr) {
            $q->where('station_number', 'LIKE', '%' . $normalizedNr . '%')
              ->orWhere('station_number_shop', 'LIKE', '%' . $normalizedNr . '%')
              ->orWhere('station_number_fuel', 'LIKE', '%' . $normalizedNr . '%');
        })->first();

        if (! $this->gasStation) {
            throw new \Exception("Keine Tankstelle mit Stationsnummer '{$this->stationNumber}' gefunden.");
        }

        $import = ArticleImport::create([
            'gas_station_id' => $this->gasStation->id,
            'station_number' => $this->stationNumber,
            'csv_printed_at' => $this->csvPrintedAt ?? now(),
            'filename' => $originalFilename,
            'status' => 'processing',
            'imported_by' => $userId,
        ]);

        try {
            $this->parseCustomerBlocks($lines);

            if (empty($this->parsedCustomers)) {
                throw new \Exception('Keine Firmenkunden in der CSV-Datei gefunden.');
            }

            DB::transaction(function () {
                $this->saveCustomers();
            });

            $import->update([
                'status' => 'completed',
                'articles_total' => count($this->parsedCustomers),
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
     * CSV einlesen mit Encoding-Konvertierung.
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
     * Kunden-Bloecke parsen.
     * Jeder Block beginnt mit einer Zeile die "Firmennummer:" enthaelt.
     */
    private function parseCustomerBlocks(array $lines): void
    {
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Header/Page-Break ueberspringen
            if ($this->isHeaderLine($line)) {
                continue;
            }

            // Neue Firmennummer erkannt → vorherigen Block abschliessen
            if (str_contains($line, 'Firmennummer:')) {
                // Vorherigen Block verarbeiten
                if ($this->currentCustomerNumber !== null && ! empty($this->currentBlock)) {
                    $this->processBlock();
                }

                // Neuen Block starten
                preg_match('/Firmennummer:\s*,+\s*(\d+)/', $line, $m);
                $this->currentCustomerNumber = isset($m[1]) ? (int) $m[1] : null;

                // Anrede steht in Spalte 1 derselben Zeile
                $cols = str_getcsv($line);
                $salutation = isset($cols[1]) ? trim($cols[1]) : null;

                $this->currentBlock = [];
                if ($salutation && ! empty($salutation)) {
                    $this->currentBlock['_salutation'] = $salutation;
                }

                continue;
            }

            // Zeile zum aktuellen Block hinzufuegen
            if ($this->currentCustomerNumber !== null) {
                $this->currentBlock[] = $line;
            }
        }

        // Letzten Block verarbeiten
        if ($this->currentCustomerNumber !== null && ! empty($this->currentBlock)) {
            $this->processBlock();
        }
    }

    /**
     * Einen Kunden-Block in strukturierte Daten umwandeln.
     */
    private function processBlock(): void
    {
        $data = [
            'customer_number' => $this->currentCustomerNumber,
            'salutation' => $this->currentBlock['_salutation'] ?? null,
            'name' => null,
            'name_2' => null,
            'department' => null,
            'street' => null,
            'zip' => null,
            'city' => null,
            'phone' => null,
            'bank_name' => null,
            'bic' => null,
            'iban' => null,
            'mandate_reference' => null,
            'contract_number' => null,
            'payment_method' => 'Bar',
            'discount_percent' => 0.00,
            'credit_limit' => 0.00,
            'prepayment' => 0.00,
            'print_delivery_note' => false,
            'print_prepayment' => false,
        ];

        // Nummerierte Zeilen durchgehen (ohne _salutation)
        $contentLines = array_filter($this->currentBlock, fn ($k) => is_int($k), ARRAY_FILTER_USE_KEY);
        $nameLines = [];
        $addressFound = false;

        foreach ($contentLines as $line) {
            $cols = str_getcsv($line);
            $col1 = isset($cols[1]) ? trim($cols[1]) : '';
            $col3 = isset($cols[3]) ? trim($cols[3]) : '';
            $col6 = isset($cols[6]) ? trim($cols[6]) : '';
            $col7 = isset($cols[7]) ? trim($cols[7]) : '';

            // Bank-Zeile
            if ($col6 === 'Bank:' || str_contains($line, 'Bank:')) {
                $data['bank_name'] = $col7 ?: null;
                // Strasse steht in col1 derselben Zeile
                if ($col1 && ! $addressFound) {
                    $data['street'] = $col1;
                    $addressFound = true;
                }
                continue;
            }

            // BIC-Zeile
            if ($col6 === 'BIC:' || str_contains($line, 'BIC:')) {
                $data['bic'] = $col7 ?: null;
                // PLZ+Stadt steht in col1 derselben Zeile
                if ($col1 && preg_match('/^(\d{5})\s+(.+)$/', $col1, $m)) {
                    $data['zip'] = $m[1];
                    $data['city'] = trim($m[2]);
                }
                continue;
            }

            // Bankleitzahl-Zeile (alt, ohne SEPA)
            if (str_contains($line, 'Bankleitzahl:')) {
                // PLZ+Stadt steht in col1
                if ($col1 && preg_match('/^(\d{5})\s+(.+)$/', $col1, $m)) {
                    $data['zip'] = $m[1];
                    $data['city'] = trim($m[2]);
                }
                continue;
            }

            // IBAN-Zeile
            if ($col6 === 'IBAN:' || str_contains($line, 'IBAN:')) {
                $data['iban'] = $col7 ?: null;
                // Telefon kann in col1 stehen
                if ($col1 && preg_match('/[\d\-\s\/]{5,}/', $col1)) {
                    $data['phone'] = $col1;
                }
                continue;
            }

            // Kontonummer-Zeile (alt)
            if (str_contains($line, 'Kontonummer:')) {
                continue;
            }

            // Mandatsreferenz
            if (str_contains($line, 'Mandatsreferenz')) {
                $data['mandate_reference'] = $col7 ?: null;
                continue;
            }

            // Vertragsnummer
            if (str_contains($line, 'Vertragsnummer:')) {
                $data['contract_number'] = $col7 ?: null;
                continue;
            }

            // Lieferschein drucken + Zahlart
            if (str_contains($line, 'Lieferschein drucken:')) {
                $data['print_delivery_note'] = ($col3 === 'Ja');
                if (str_contains($line, 'Zahlart:')) {
                    $data['payment_method'] = $col7 ?: 'Bar';
                }
                continue;
            }

            // Vorauszahlung drucken + Skonto
            if (str_contains($line, 'Vorauszahlung drucken:')) {
                $data['print_prepayment'] = ($col3 === 'Ja');
                if (str_contains($line, 'Skonto:')) {
                    $data['discount_percent'] = $this->parseGermanDecimal(str_replace('%', '', $col7)) ?? 0.00;
                }
                continue;
            }

            // Vorauszahlung + Limit
            if (str_contains($line, 'Vorauszahlung:') && ! str_contains($line, 'drucken')) {
                $data['prepayment'] = $this->parseGermanDecimal(str_replace('EUR', '', $col3)) ?? 0.00;
                if (str_contains($line, 'Limit:')) {
                    $data['credit_limit'] = $this->parseGermanDecimal(str_replace('EUR', '', $col7)) ?? 0.00;
                }
                continue;
            }

            // Leerzeile oder unbekannte Zeile mit leerem Inhalt
            if (empty($col1) && empty($col6)) {
                continue;
            }

            // Name-Zeilen sammeln (alles was nicht erkannt wurde)
            if ($col1 && ! $addressFound) {
                // Pruefen ob es eine PLZ+Stadt Zeile ist
                if (preg_match('/^(\d{5})\s+(.+)$/', $col1, $m)) {
                    $data['zip'] = $m[1];
                    $data['city'] = trim($m[2]);
                    $addressFound = true;
                } else {
                    $nameLines[] = $col1;
                }
            }
        }

        // Name-Zeilen zuordnen
        if (count($nameLines) >= 1) {
            $data['name'] = $nameLines[0];
        }
        if (count($nameLines) >= 2) {
            // Zweite Zeile: Pruefe ob es eine Adresse oder Abteilung ist
            $second = $nameLines[1];
            if (preg_match('/\d/', $second) && (str_contains($second, 'str') || str_contains($second, 'Str') || str_contains($second, 'weg') || str_contains($second, 'platz') || str_contains($second, 'allee') || preg_match('/\d+[a-z]?\s*$/', $second))) {
                // Sieht wie eine Strasse aus
                $data['street'] = $second;
                $addressFound = true;
            } else {
                $data['department'] = $second;
            }
        }
        if (count($nameLines) >= 3 && ! $addressFound) {
            // Dritte Zeile als Strasse
            $data['street'] = $nameLines[2];
        }

        // Fallback: Strasse aus Bank-Zeile (col1) wurde bereits oben gesetzt

        if ($data['name']) {
            $this->parsedCustomers[$this->currentCustomerNumber] = $data;
        }
    }

    /**
     * Header/Page-Break erkennen.
     */
    private function isHeaderLine(string $line): bool
    {
        $patterns = [
            'Aral STATION',
            'Schlitzer Str',
            'Firmenliste',
            'Selektion:',
            'Sortierung:',
            'Stationsnummer:',
            'Tel:',
            'Fax:',
        ];

        foreach ($patterns as $p) {
            if (str_contains($line, $p)) {
                return true;
            }
        }

        // PLZ-Zeile ohne Komma am Anfang (Header-PLZ, nicht Kunden-PLZ)
        if (preg_match('/^\d{5}\s+\w+/', $line) && ! str_starts_with($line, ',')) {
            return true;
        }

        return false;
    }

    /**
     * Deutsches Dezimalformat parsen.
     */
    private function parseGermanDecimal(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        $value = trim($value, '"');
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Geparste Kunden in DB speichern.
     */
    private function saveCustomers(): void
    {
        foreach ($this->parsedCustomers as $customerNumber => $data) {
            $existing = CorporateCustomer::where('gas_station_id', $this->gasStation->id)
                ->where('customer_number', $customerNumber)
                ->first();

            $saveData = [
                'salutation' => $data['salutation'],
                'name' => $data['name'],
                'name_2' => $data['name_2'],
                'department' => $data['department'],
                'street' => $data['street'],
                'zip' => $data['zip'],
                'city' => $data['city'],
                'phone' => $data['phone'],
                'iban' => $data['iban'],
                'bic' => $data['bic'],
                'bank_name' => $data['bank_name'],
                'mandate_reference' => $data['mandate_reference'],
                'contract_number' => $data['contract_number'],
                'payment_method' => $data['payment_method'],
                'discount_percent' => $data['discount_percent'],
                'credit_limit' => $data['credit_limit'],
                'prepayment' => $data['prepayment'],
                'print_delivery_note' => $data['print_delivery_note'],
                'print_prepayment' => $data['print_prepayment'],
                'csv_printed_at' => $this->csvPrintedAt,
                'imported_at' => now(),
            ];

            if (! $existing) {
                $defaultEmail = \App\Models\InvoiceSetting::get('default_customer_email', CorporateCustomer::PLACEHOLDER_EMAIL);
                CorporateCustomer::create(array_merge($saveData, [
                    'gas_station_id' => $this->gasStation->id,
                    'customer_number' => $customerNumber,
                    'email' => $defaultEmail,
                ]));
                $this->created++;
            } else {
                // Pruefen ob sich etwas geaendert hat
                $changed = false;
                $checkFields = ['name', 'name_2', 'department', 'street', 'zip', 'city', 'phone',
                    'payment_method', 'mandate_reference', 'contract_number',
                    'print_delivery_note', 'print_prepayment'];

                foreach ($checkFields as $field) {
                    if (($existing->$field ?? '') != ($saveData[$field] ?? '')) {
                        $changed = true;
                        break;
                    }
                }

                // Dezimal-Felder separat pruefen
                if (! $changed) {
                    foreach (['discount_percent', 'credit_limit', 'prepayment'] as $field) {
                        if (bccomp((string) ($existing->$field ?? 0), (string) ($saveData[$field] ?? 0), 2) !== 0) {
                            $changed = true;
                            break;
                        }
                    }
                }

                // Bankdaten nur updaten wenn CSV sie liefert und bestehende leer
                if (! empty($saveData['iban']) && empty($existing->iban)) {
                    $changed = true;
                } elseif (! empty($existing->iban)) {
                    // Bestehende Bankdaten nicht ueberschreiben wenn bereits vorhanden
                    unset($saveData['iban'], $saveData['bic'], $saveData['bank_name']);
                }

                if ($changed) {
                    $existing->update($saveData);
                    $this->updated++;

                    Log::info('CSV Merge: Kundendaten aktualisiert', [
                        'kunde' => $customerNumber,
                    ]);
                } else {
                    $this->unchanged++;
                }
            }
        }
    }

    /**
     * Nur Metadaten extrahieren.
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
