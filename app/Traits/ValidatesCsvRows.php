<?php

namespace App\Traits;

/**
 * Trait fuer CSV-Import Services.
 * Bietet Zeilen-Validierung und Statistik-Tracking.
 */
trait ValidatesCsvRows
{
    /** Zaehler fuer uebersprungene Zeilen */
    protected int $skippedRows = 0;

    /**
     * Prueft ob eine CSV-Zeile die erwartete Mindestanzahl Spalten hat.
     *
     * @param  array  $row  Die CSV-Zeile als Array
     * @param  int  $expectedMinColumns  Mindestanzahl erwarteter Spalten
     * @param  int  $lineNumber  Zeilennummer fuer Logging
     * @return bool true wenn gueltig, false wenn ungueltig
     */
    protected function validateRow(array $row, int $expectedMinColumns, int $lineNumber = 0): bool
    {
        if (count($row) < $expectedMinColumns) {
            $this->skippedRows++;
            \Illuminate\Support\Facades\Log::debug('CSV-Import: Zeile uebersprungen (zu wenige Spalten)', [
                'service' => static::class,
                'zeile' => $lineNumber,
                'spalten_erwartet' => $expectedMinColumns,
                'spalten_vorhanden' => count($row),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Prueft ob der CSV-Inhalt nicht leer ist (mindestens Header + 1 Datenzeile).
     *
     * @param  string  $content  Der CSV-Dateiinhalt
     * @param  int  $minLines  Mindestanzahl Zeilen (Standard: 2 = Header + 1 Datenzeile)
     * @return bool true wenn genug Zeilen vorhanden
     */
    protected function validateCsvNotEmpty(string $content, int $minLines = 2): bool
    {
        $lineCount = substr_count(trim($content), "\n") + 1;

        if ($lineCount < $minLines) {
            \Illuminate\Support\Facades\Log::warning('CSV-Import: Datei zu wenige Zeilen', [
                'service' => static::class,
                'zeilen' => $lineCount,
                'minimum' => $minLines,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Gibt Anzahl uebersprungener Zeilen zurueck.
     */
    public function getSkippedRows(): int
    {
        return $this->skippedRows;
    }
}
