<?php

namespace App\Services\Kiosk;

use App\Models\Kiosk\Article;
use App\Models\Kiosk\ArticleIssue;
use App\Models\Kiosk\Import;
use App\Models\Kiosk\Invoice;
use App\Models\Kiosk\OrderLine;
use App\Models\Kiosk\PriceChangeLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PVG-Rechnungs-Parser (PDF -> Text -> Positionen).
 *
 * Konvertiert PVG-Rechnungen mit pdftotext in Text und parst die
 * Positionen als 5-Zeilen-Quintupel (Typ + LS/Paket + Datum / Objekt +
 * Bezeichnung / EAN + Ausgabe / Menge + EP + MwSt / Gesamtbetrag).
 */
class PvgPdfParserService
{
    public function __construct(
        private readonly EanInspectorService $eanInspector,
    ) {}

    /**
     * Komplette PVG-PDF importieren.
     *
     * @return array{success: bool, invoice_id?: string, articles_inserted: int, articles_updated: int, articles_skipped: int, message?: string}
     */
    public function import(string $pdfPath, string $tenantId, ?string $originalFilename = null): array
    {
        $hash = hash_file('sha256', $pdfPath);

        // Duplikat-Check
        $existing = Import::where('tenant_id', $tenantId)
            ->where('file_hash', $hash)
            ->where('status', 'success')
            ->first();
        if ($existing) {
            return [
                'success' => false,
                'articles_inserted' => 0,
                'articles_updated' => 0,
                'articles_skipped' => 0,
                'message' => 'Datei wurde bereits importiert.',
            ];
        }

        try {
            $text = $this->extractText($pdfPath);
            $lines = $this->normalizeLines($text);

            $header = $this->parseHeader($lines);
            $positions = $this->parsePositions($lines);

            return DB::transaction(function () use ($header, $positions, $tenantId, $hash, $originalFilename) {
                $invoice = Invoice::firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'supplier' => 'PVG',
                        'rechnungsnummer' => $header['rechnungsnummer'] ?? 'UNBEKANNT-' . substr($hash, 0, 8),
                    ],
                    [
                        'rechnungsdatum' => $header['rechnungsdatum'] ?? null,
                        'lieferdatum_von' => $header['lieferdatum_von'] ?? null,
                        'lieferdatum_bis' => $header['lieferdatum_bis'] ?? null,
                        'kundennummer' => $header['kundennummer'] ?? null,
                        'gesamtbetrag' => $header['gesamtbetrag'] ?? null,
                        'filename' => $originalFilename,
                        'file_hash' => $hash,
                    ],
                );

                $stats = ['articles_inserted' => 0, 'articles_updated' => 0, 'articles_skipped' => 0];

                foreach ($positions as $pos) {
                    $this->processPosition($pos, $invoice, $tenantId, $stats);
                }

                Import::create([
                    'tenant_id' => $tenantId,
                    'invoice_id' => $invoice->id,
                    'filename' => $originalFilename,
                    'file_hash' => $hash,
                    'status' => 'success',
                    'articles_inserted' => $stats['articles_inserted'],
                    'articles_updated' => $stats['articles_updated'],
                    'articles_skipped' => $stats['articles_skipped'],
                    'created_at' => now(),
                ]);

                return array_merge(['success' => true, 'invoice_id' => $invoice->id], $stats);
            });
        } catch (\Throwable $e) {
            Log::error('PVG PDF Import fehlgeschlagen', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'file' => $originalFilename,
            ]);

            Import::create([
                'tenant_id' => $tenantId,
                'filename' => $originalFilename,
                'file_hash' => $hash,
                'status' => 'error',
                'error_message' => substr($e->getMessage(), 0, 500),
                'created_at' => now(),
            ]);

            return [
                'success' => false,
                'articles_inserted' => 0,
                'articles_updated' => 0,
                'articles_skipped' => 0,
                'message' => 'Fehler beim Parsen: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * pdftotext als externen Prozess aufrufen.
     */
    private function extractText(string $pdfPath): string
    {
        $output = [];
        $code = 0;
        @exec('pdftotext -layout ' . escapeshellarg($pdfPath) . ' - 2>&1', $output, $code);

        if ($code !== 0 || empty($output)) {
            // Fallback: ohne -layout
            $output = [];
            @exec('pdftotext ' . escapeshellarg($pdfPath) . ' - 2>&1', $output, $code);
        }

        if ($code !== 0 || empty($output)) {
            throw new \RuntimeException('pdftotext nicht verfuegbar oder PDF unlesbar.');
        }

        return implode("\n", $output);
    }

    /**
     * Text in Zeilen aufteilen, Leerzeilen entfernen, trimmen.
     *
     * @return array<int, string>
     */
    private function normalizeLines(string $text): array
    {
        return collect(explode("\n", $text))
            ->map(fn ($l) => trim($l))
            ->filter(fn ($l) => $l !== '')
            ->values()
            ->all();
    }

    /**
     * Rechnungskopf extrahieren.
     */
    private function parseHeader(array $lines): array
    {
        $header = [];
        $text = implode("\n", $lines);

        if (preg_match('/Rechnung\s*Nr\.?\s*(\S+)/i', $text, $m)) {
            $header['rechnungsnummer'] = trim($m[1]);
        }
        if (preg_match('/Rechnungsdatum:?\s*(\d{2}\.\d{2}\.\d{4})/i', $text, $m)) {
            $header['rechnungsdatum'] = $this->parseDate($m[1]);
        }
        if (preg_match('/Kunden-?Nr\.?:?\s*(\S+)/i', $text, $m)) {
            $header['kundennummer'] = trim($m[1]);
        }
        if (preg_match('/Lieferzeitraum:?\s*(\d{2}\.\d{2}\.\d{4})\s*-\s*(\d{2}\.\d{2}\.\d{4})/i', $text, $m)) {
            $header['lieferdatum_von'] = $this->parseDate($m[1]);
            $header['lieferdatum_bis'] = $this->parseDate($m[2]);
        }
        if (preg_match('/Gesamtbetrag:?\s*([\d\.\,]+)/i', $text, $m)) {
            $header['gesamtbetrag'] = (float) str_replace(['.', ','], ['', '.'], $m[1]);
        }

        return $header;
    }

    /**
     * Positionen als 5-Zeilen-Bloecke parsen.
     *
     * @return array<int, array>
     */
    private function parsePositions(array $lines): array
    {
        $positions = [];
        $count = count($lines);

        for ($i = 0; $i < $count - 4; $i++) {
            $line1 = $lines[$i];

            // Zeile 1 muss mit "Lieferung" oder "Remission" beginnen
            if (! preg_match('/^(Lieferung|Remission)\s+(LS|PK)\s*(\S+)\s+(\d{2}\.\d{2}\.\d{4})/i', $line1, $m1)) {
                continue;
            }

            $typ = strtolower($m1[1]) === 'lieferung' ? 'lieferung' : 'remission';
            $isLieferung = $typ === 'lieferung';

            // Zeile 2: Objektnummer + Bezeichnung
            if (! preg_match('/^(\d{3,6})\s+(.+)$/', $lines[$i + 1], $m2)) {
                continue;
            }

            // Zeile 3: EAN + Ausgabe (KW XX/YYYY)
            if (! preg_match('/^(\d{13})\s+KW\s*(\d{1,2})\/(\d{4})/i', $lines[$i + 2], $m3)) {
                continue;
            }

            // Zeile 4: Menge + EP + MwSt
            if (! preg_match('/^(\d+)\s+([\d\,\.]+)\s*netto\s+(\d+)\s*%/i', $lines[$i + 3], $m4)) {
                continue;
            }

            // Zeile 5: Gesamtbetrag
            if (! preg_match('/^([\d\,\.]+)\s*netto/i', $lines[$i + 4], $m5)) {
                continue;
            }

            $positions[] = [
                'typ' => $typ,
                'lieferschein_nr' => $isLieferung ? trim($m1[3]) : null,
                'paket' => ! $isLieferung ? trim($m1[3]) : null,
                'lieferschein_datum' => $this->parseDate($m1[4]),
                'objekt' => trim($m2[1]),
                'bezeichnung' => trim($m2[2]),
                'ean' => trim($m3[1]),
                'ausgabe' => str_pad($m3[2], 4, '0', STR_PAD_LEFT),
                'menge' => $isLieferung ? (int) $m4[1] : -1 * (int) $m4[1],
                'einzelpreis_netto' => (float) str_replace(',', '.', $m4[2]),
                'mwst_satz' => (float) $m4[3],
                'gesamt_netto' => (float) str_replace(',', '.', $m5[1]),
            ];

            $i += 4;
        }

        return $positions;
    }

    /**
     * Eine Position verarbeiten: Artikel + OrderLine speichern.
     */
    private function processPosition(array $pos, Invoice $invoice, string $tenantId, array &$stats): void
    {
        $eanInfo = $this->eanInspector->inspect($pos['ean']);
        $vkpBrutto = $eanInfo['preis_brutto'] ?? 0;
        $vkpNetto = $eanInfo['preis_netto'] ?? 0;
        $mwstSatz = $eanInfo['mwst_satz'] ?? $pos['mwst_satz'];

        $article = Article::where('tenant_id', $tenantId)
            ->where('supplier', 'PVG')
            ->where('objekt', $pos['objekt'])
            ->first();

        if (! $article) {
            $article = Article::create([
                'tenant_id' => $tenantId,
                'supplier' => 'PVG',
                'objekt' => $pos['objekt'],
                'ean' => $pos['ean'],
                'bezeichnung' => $pos['bezeichnung'],
                'aktueller_preis_brutto' => $vkpBrutto,
                'aktueller_preis_netto' => $vkpNetto,
                'mwst_satz' => $mwstSatz,
                'ek' => $pos['einzelpreis_netto'],
                'is_pending' => false,
                'last_seen_at' => now(),
            ]);
            $stats['articles_inserted']++;
        } else {
            $changes = [];
            if (abs((float) $article->aktueller_preis_netto - $vkpNetto) > 0.001) {
                $changes['old_preis_netto'] = $article->aktueller_preis_netto;
                $changes['new_preis_netto'] = $vkpNetto;
                $changes['old_preis_brutto'] = $article->aktueller_preis_brutto;
                $changes['new_preis_brutto'] = $vkpBrutto;
            }
            if ($article->ek === null || abs((float) $article->ek - $pos['einzelpreis_netto']) > 0.001) {
                $changes['old_ek'] = $article->ek;
                $changes['new_ek'] = $pos['einzelpreis_netto'];
            }

            $article->update([
                'ean' => $pos['ean'],
                'bezeichnung' => $pos['bezeichnung'],
                'aktueller_preis_brutto' => $vkpBrutto,
                'aktueller_preis_netto' => $vkpNetto,
                'mwst_satz' => $mwstSatz,
                'ek' => $pos['einzelpreis_netto'],
                'is_pending' => false,
                'last_seen_at' => now(),
            ]);

            if (! empty($changes)) {
                PriceChangeLog::create(array_merge($changes, [
                    'article_id' => $article->id,
                    'change_type' => isset($changes['new_preis_netto']) && isset($changes['new_ek']) ? 'multi'
                        : (isset($changes['new_preis_netto']) ? 'vkp' : 'ek'),
                    'source' => 'invoice_import',
                    'invoice_id' => $invoice->id,
                    'changed_at' => now(),
                ]));
            }
            $stats['articles_updated']++;
        }

        // Ausgabe speichern (UPSERT)
        ArticleIssue::firstOrCreate(
            ['article_id' => $article->id, 'ausgabe' => $pos['ausgabe']],
            ['first_seen_at' => now(), 'last_seen_at' => now()],
        )->update(['last_seen_at' => now()]);

        // OrderLine speichern
        OrderLine::create([
            'invoice_id' => $invoice->id,
            'article_id' => $article->id,
            'typ' => $pos['typ'],
            'lieferschein_nr' => $pos['lieferschein_nr'],
            'lieferschein_datum' => $pos['lieferschein_datum'],
            'paket' => $pos['paket'],
            'ausgabe' => $pos['ausgabe'],
            'menge' => $pos['menge'],
            'einzelpreis_netto' => $pos['einzelpreis_netto'],
            'einzelpreis_brutto' => $vkpBrutto,
            'mwst_satz' => $mwstSatz,
            'gesamt_netto' => $pos['gesamt_netto'],
            'gesamt_brutto' => round($pos['gesamt_netto'] * (1 + $mwstSatz / 100), 4),
        ]);
    }

    private function parseDate(string $dmy): ?string
    {
        try {
            return \Carbon\Carbon::createFromFormat('d.m.Y', $dmy)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
