<?php

namespace App\Services;

use App\Models\ArticleImport;
use App\Models\CorporateCustomer;
use App\Models\FuelCard;
use App\Models\GasStation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service fuer den Import von Tankkarten/Teilnehmern aus CSV-Dateien (PtcptList).
 *
 * CSV-Format: Multi-line Bloecke pro Firma mit Teilnehmer-Zeilen.
 * Erkennung: CSV-Header enthaelt "Teilnehmerliste".
 * Muss NACH CorporateCustomerCsvImportService laufen (Prioritaet 6 > 5).
 */
class FuelCardCsvImportService
{
    use \App\Traits\ValidatesCsvRows;

    private ?string $stationNumber = null;
    private ?Carbon $csvPrintedAt = null;
    private ?GasStation $gasStation = null;

    // Geparste Karten: [customer_number => [cards...]]
    private array $parsedCards = [];
    private ?int $currentCustomerNumber = null;
    private bool $inParticipantSection = false;

    // Statistik
    private int $created = 0;
    private int $updated = 0;
    private int $unchanged = 0;
    private int $skippedNoCustomer = 0;

    /**
     * Teilnehmerliste importieren.
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
            $this->parseParticipantBlocks($lines);

            $totalCards = 0;
            foreach ($this->parsedCards as $cards) {
                $totalCards += count($cards);
            }

            if ($totalCards === 0) {
                throw new \Exception('Keine Tankkarten in der CSV-Datei gefunden.');
            }

            DB::transaction(function () {
                $this->saveCards();
            });

            $import->update([
                'status' => 'completed',
                'articles_total' => $totalCards,
                'articles_created' => $this->created,
                'articles_updated' => $this->updated,
                'articles_unchanged' => $this->unchanged,
                'articles_not_found' => $this->skippedNoCustomer,
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
        // Non-Breaking Spaces durch normale Leerzeichen ersetzen (CSV-Artefakt)
        $content = str_replace("\xC2\xA0", ' ', $content);

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
                    // ignorieren
                }
            }
            if ($this->stationNumber && $this->csvPrintedAt) {
                break;
            }
        }
    }

    /**
     * Teilnehmer-Bloecke parsen.
     *
     * Struktur pro Firma:
     *   ,Firma/Anrede,,,,Firmennummer:,,NR
     *   ,Name...
     *   ,Adresse...
     *   ,Teilnehmer,Fahrer / Kennzeichen,,,,,Zusatzvermerke
     *   ,CARD_NR, FAHRER / KENNZEICHEN,,,,,ZUSATZ
     *   ,CARD_NR, FAHRER / KENNZEICHEN,,,,,ZUSATZ
     *   ... (naechste Firma)
     */
    private function parseParticipantBlocks(array $lines): void
    {
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Header/Page-Break
            if ($this->isHeaderLine($line)) {
                continue;
            }

            // Neue Firmennummer erkannt
            if (str_contains($line, 'Firmennummer:')) {
                preg_match('/Firmennummer:\s*,+\s*(\d+)/', $line, $m);
                $this->currentCustomerNumber = isset($m[1]) ? (int) $m[1] : null;
                $this->inParticipantSection = false;
                continue;
            }

            // Teilnehmer-Header erkennen
            if (str_contains($line, 'Teilnehmer') && str_contains($line, 'Fahrer')) {
                $this->inParticipantSection = true;
                continue;
            }

            // Teilnehmer-Zeilen parsen
            if ($this->inParticipantSection && $this->currentCustomerNumber !== null) {
                $cols = str_getcsv($line);
                $cardNrStr = isset($cols[1]) ? trim($cols[1]) : '';

                // Teilnehmer-Nr muss numerisch sein
                if (! is_numeric($cardNrStr)) {
                    // Nicht-numerisch = naechste Firma oder anderer Inhalt
                    $this->inParticipantSection = false;
                    continue;
                }

                $cardNumber = (int) $cardNrStr;
                $driverKennzeichen = isset($cols[2]) ? trim($cols[2]) : '';
                $zusatz = isset($cols[7]) ? trim($cols[7]) : '';

                // "Fahrer / Kennzeichen" aufsplitten
                // Format: "Name / Plate", "Name / " (kein Kennzeichen), "Name /", " / "
                $driverName = null;
                $licensePlate = null;

                if (str_contains($driverKennzeichen, '/')) {
                    $parts = explode('/', $driverKennzeichen, 2);
                    $driverName = trim($parts[0]) ?: null;
                    $licensePlate = trim($parts[1]) ?: null;
                } elseif (! empty($driverKennzeichen)) {
                    $driverName = trim($driverKennzeichen) ?: null;
                }

                if (! isset($this->parsedCards[$this->currentCustomerNumber])) {
                    $this->parsedCards[$this->currentCustomerNumber] = [];
                }

                $this->parsedCards[$this->currentCustomerNumber][] = [
                    'card_number' => $cardNumber,
                    'driver_name' => $driverName,
                    'license_plate' => $licensePlate,
                    'notes' => $zusatz ?: null,
                ];
            }
        }
    }

    private function isHeaderLine(string $line): bool
    {
        $patterns = [
            'Aral STATION',
            'Schlitzer Str',
            'Teilnehmerliste',
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

        if (preg_match('/^\d{5}\s+\w+/', $line) && ! str_starts_with($line, ',')) {
            return true;
        }

        return false;
    }

    /**
     * Tankkarten in DB speichern.
     */
    private function saveCards(): void
    {
        foreach ($this->parsedCards as $customerNumber => $cards) {
            // Firmenkunden finden
            $customer = CorporateCustomer::where('gas_station_id', $this->gasStation->id)
                ->where('customer_number', $customerNumber)
                ->first();

            if (! $customer) {
                Log::info("Tankkarten-Import: Firmenkunde {$customerNumber} nicht gefunden (Station: {$this->gasStation->name})");
                $this->skippedNoCustomer += count($cards);
                continue;
            }

            foreach ($cards as $cardData) {
                $existing = FuelCard::where('gas_station_id', $this->gasStation->id)
                    ->where('card_number', $cardData['card_number'])
                    ->first();

                $saveData = [
                    'driver_name' => $cardData['driver_name'],
                    'license_plate' => $cardData['license_plate'],
                    'notes' => $cardData['notes'],
                    'imported_at' => now(),
                ];

                if (! $existing) {
                    FuelCard::create(array_merge($saveData, [
                        'gas_station_id' => $this->gasStation->id,
                        'corporate_customer_id' => $customer->id,
                        'card_number' => $cardData['card_number'],
                    ]));
                    $this->created++;
                } else {
                    // Ggf. Customer-Zuordnung aktualisieren
                    $saveData['corporate_customer_id'] = $customer->id;

                    $changed = false;
                    foreach (['driver_name', 'license_plate', 'notes'] as $f) {
                        if (($existing->$f ?? '') != ($saveData[$f] ?? '')) {
                            $changed = true;
                            break;
                        }
                    }

                    if ($changed || $existing->corporate_customer_id !== $customer->id) {
                        $existing->update($saveData);
                        $this->updated++;
                    } else {
                        $this->unchanged++;
                    }
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
