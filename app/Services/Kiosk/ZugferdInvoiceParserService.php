<?php

namespace App\Services\Kiosk;

use App\Models\Kiosk\Article;
use App\Models\Kiosk\ArticleIssue;
use App\Models\Kiosk\Import;
use App\Models\Kiosk\Invoice;
use App\Models\Kiosk\OrderLine;
use App\Models\Kiosk\PriceChangeLog;
use App\Models\Kiosk\Supplier;
use App\Models\Kiosk\SupplierStation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ZUGFeRD/Factur-X Parser fuer eingebettete CrossIndustryInvoice XML
 * (urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100).
 *
 * PVG verschickt seit Mai 2026 ZUGFeRD-Rechnungen.
 */
class ZugferdInvoiceParserService
{
    public function __construct(
        private readonly EanInspectorService $eanInspector,
    ) {}

    /**
     * @return array{success: bool, invoice_id?: string, articles_inserted: int, articles_updated: int, articles_skipped: int, message?: string}
     */
    public function import(string $pdfPath, string $tenantId, ?string $originalFilename = null): array
    {
        $hash = hash_file('sha256', $pdfPath);

        // Duplikat-Check
        if (Import::where('tenant_id', $tenantId)->where('file_hash', $hash)->where('status', 'success')->exists()) {
            return [
                'success' => false,
                'articles_inserted' => 0,
                'articles_updated' => 0,
                'articles_skipped' => 0,
                'message' => 'Datei wurde bereits importiert.',
            ];
        }

        try {
            $xml = $this->extractXml($pdfPath);
            if (! $xml) {
                throw new \RuntimeException('Kein ZUGFeRD-XML in PDF gefunden.');
            }

            $header = $this->parseHeader($xml);
            $sellerInfo = $this->parseSeller($xml);
            $positions = $this->parseLineItems($xml);

            if (empty($positions)) {
                Log::warning('ZUGFeRD-Parser: Keine LineItems', [
                    'file' => $originalFilename,
                    'header' => $header,
                ]);
            }

            return DB::transaction(function () use ($header, $sellerInfo, $positions, $tenantId, $hash, $originalFilename) {
                // Lieferant erkennen oder anlegen
                $supplier = $this->findOrCreateSupplier($tenantId, $sellerInfo);

                // Tankstelle ueber Kundennummer aufloesen
                $gasStationId = null;
                $kundennummer = $header['kundennummer'] ?? null;
                if ($supplier && $kundennummer) {
                    $pivot = SupplierStation::where('supplier_id', $supplier->id)
                        ->where('kundennummer', $kundennummer)
                        ->first();
                    $gasStationId = $pivot?->gas_station_id;
                }

                $invoice = Invoice::firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'supplier' => $supplier?->short_code ?? 'PVG',
                        'rechnungsnummer' => $header['rechnungsnummer'] ?? 'UNBEKANNT-' . substr($hash, 0, 8),
                    ],
                    [
                        'supplier_id' => $supplier?->id,
                        'gas_station_id' => $gasStationId,
                        'kundennummer' => $kundennummer,
                        'rechnungsdatum' => $header['rechnungsdatum'] ?? null,
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
            Log::error('ZUGFeRD Import fehlgeschlagen', [
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
                'message' => 'Fehler: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Erkennt ob ein PDF ZUGFeRD-XML enthält.
     */
    public function hasEmbeddedXml(string $pdfPath): bool
    {
        $content = @file_get_contents($pdfPath);
        if (! $content) return false;
        return (bool) preg_match('/<rsm:CrossIndustryInvoice/', $content);
    }

    /**
     * Extrahiert ZUGFeRD-XML aus dem PDF.
     */
    private function extractXml(string $pdfPath): ?\SimpleXMLElement
    {
        $content = file_get_contents($pdfPath);
        if (! $content) return null;

        if (! preg_match('/<\?xml[\s\S]*?<\/rsm:CrossIndustryInvoice>/', $content, $m)) {
            return null;
        }

        $xml = @simplexml_load_string($m[0]);
        if (! $xml) return null;

        $ns = $xml->getNamespaces(true);
        if (isset($ns['ram'])) $xml->registerXPathNamespace('ram', $ns['ram']);
        if (isset($ns['rsm'])) $xml->registerXPathNamespace('rsm', $ns['rsm']);
        if (isset($ns['udt'])) $xml->registerXPathNamespace('udt', $ns['udt']);

        return $xml;
    }

    /**
     * Verkaeufer (Lieferant) aus XML extrahieren.
     */
    private function parseSeller(\SimpleXMLElement $xml): array
    {
        $info = ['name' => null, 'vat_id' => null, 'email' => null, 'phone' => null, 'address' => null];

        $name = $xml->xpath('//ram:SellerTradeParty/ram:Name')[0] ?? null;
        if ($name) $info['name'] = trim((string) $name);

        $vat = $xml->xpath('//ram:SellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID="VA"]')[0] ?? null;
        if ($vat) $info['vat_id'] = (string) $vat;

        $email = $xml->xpath('//ram:SellerTradeParty//ram:URIID[@schemeID="EM"]')[0] ?? null;
        if ($email) $info['email'] = (string) $email;

        $phone = $xml->xpath('//ram:SellerTradeParty//ram:CompleteNumber')[0] ?? null;
        if ($phone) $info['phone'] = (string) $phone;

        $street = $xml->xpath('//ram:SellerTradeParty/ram:PostalTradeAddress/ram:LineOne')[0] ?? null;
        $plz = $xml->xpath('//ram:SellerTradeParty/ram:PostalTradeAddress/ram:PostcodeCode')[0] ?? null;
        $city = $xml->xpath('//ram:SellerTradeParty/ram:PostalTradeAddress/ram:CityName')[0] ?? null;
        $addr = trim(implode(', ', array_filter([
            $street ? (string) $street : null,
            $plz && $city ? "{$plz} {$city}" : ($city ? (string) $city : null),
        ])));
        if ($addr !== '') $info['address'] = $addr;

        return $info;
    }

    /**
     * Lieferant ueber Name (oder VAT-ID) finden oder neu anlegen.
     */
    private function findOrCreateSupplier(string $tenantId, array $sellerInfo): ?Supplier
    {
        $name = $sellerInfo['name'] ?? null;
        if (! $name) return null;

        // Versuche zuerst per VAT-ID, dann per Name (case-insensitive)
        $supplier = null;
        if (! empty($sellerInfo['vat_id'])) {
            $supplier = Supplier::where('tenant_id', $tenantId)
                ->where('vat_id', $sellerInfo['vat_id'])
                ->first();
        }
        if (! $supplier) {
            $supplier = Supplier::where('tenant_id', $tenantId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->first();
        }

        if ($supplier) {
            // Update bei Bedarf
            $supplier->update(array_filter([
                'vat_id' => $sellerInfo['vat_id'] ?: $supplier->vat_id,
                'email' => $sellerInfo['email'] ?: $supplier->email,
                'phone' => $sellerInfo['phone'] ?: $supplier->phone,
                'address' => $sellerInfo['address'] ?: $supplier->address,
            ]));
            return $supplier;
        }

        // Short-Code aus Name ableiten (z.B. "PVG Presse-Vertrieb GmbH" -> "PVG")
        $shortCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 10));
        // Eindeutigkeit sicherstellen
        $base = $shortCode;
        $i = 1;
        while (Supplier::where('tenant_id', $tenantId)->where('short_code', $shortCode)->exists()) {
            $shortCode = $base . $i++;
        }

        return Supplier::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'short_code' => $shortCode ?: null,
            'vat_id' => $sellerInfo['vat_id'] ?? null,
            'email' => $sellerInfo['email'] ?? null,
            'phone' => $sellerInfo['phone'] ?? null,
            'address' => $sellerInfo['address'] ?? null,
            'is_active' => true,
        ]);
    }

    /**
     * Rechnungskopf aus XML extrahieren.
     */
    private function parseHeader(\SimpleXMLElement $xml): array
    {
        $header = [];

        $rechnungsNr = $xml->xpath('//ram:ExchangedDocument/ram:ID')[0] ?? null;
        if ($rechnungsNr) $header['rechnungsnummer'] = (string) $rechnungsNr;

        $datum = $xml->xpath('//ram:ExchangedDocument/ram:IssueDateTime/udt:DateTimeString')[0] ?? null;
        if ($datum) {
            try {
                $header['rechnungsdatum'] = Carbon::createFromFormat('Ymd', (string) $datum)->format('Y-m-d');
            } catch (\Throwable) {}
        }

        $gesamt = $xml->xpath('//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount')[0] ?? null;
        if ($gesamt) $header['gesamtbetrag'] = (float) $gesamt;

        // Kundennummer: BuyerTradeParty/ID (kann auch BuyerAssignedID am Verkaufspartner sein)
        $kunde = $xml->xpath('//ram:BuyerTradeParty/ram:ID')[0]
            ?? $xml->xpath('//ram:BuyerTradeParty/ram:GlobalID')[0]
            ?? null;
        if ($kunde) $header['kundennummer'] = trim((string) $kunde);

        return $header;
    }

    /**
     * LineItems aus XML extrahieren.
     */
    private function parseLineItems(\SimpleXMLElement $xml): array
    {
        $positions = [];
        foreach ($xml->xpath('//ram:IncludedSupplyChainTradeLineItem') as $line) {
            $line->registerXPathNamespace('ram', $xml->getNamespaces(true)['ram']);

            $note = trim((string) ($line->xpath('.//ram:IncludedNote/ram:Content')[0] ?? ''));
            $isLieferung = stripos($note, 'lieferschein') === 0;
            $isRemission = stripos($note, 'remission') === 0;
            if (! $isLieferung && ! $isRemission) continue;

            // Lieferschein-Nr aus Note: "Lieferschein #8057 vom 27.04.2026"
            $lieferscheinNr = null;
            if ($isLieferung && preg_match('/#?(\d+)/', $note, $m)) {
                $lieferscheinNr = $m[1];
            }

            // Datum aus Note
            $datum = null;
            if (preg_match('/(\d{2}\.\d{2}\.\d{4})/', $note, $m)) {
                try {
                    $datum = Carbon::createFromFormat('d.m.Y', $m[1])->format('Y-m-d');
                } catch (\Throwable) {}
            }

            $ean = (string) ($line->xpath('.//ram:SpecifiedTradeProduct/ram:GlobalID')[0] ?? '');
            $objekt = (string) ($line->xpath('.//ram:SpecifiedTradeProduct/ram:SellerAssignedID')[0] ?? '');
            $bezeichnung = (string) ($line->xpath('.//ram:SpecifiedTradeProduct/ram:Name')[0] ?? '');
            $weekday = $this->extractWeekday($bezeichnung);

            // FIXED_PRICE = VKP brutto
            $fixedPrice = null;
            foreach ($line->xpath('.//ram:ApplicableProductCharacteristic') as $char) {
                $desc = (string) ($char->xpath('./ram:Description')[0] ?? '');
                if ($desc === 'FIXED_PRICE') {
                    $fixedPrice = (float) ($char->xpath('./ram:Value')[0] ?? 0);
                }
            }

            // Ausgabe aus ClassCode listID=SN, z.B. 202600018 -> "0018"
            $classCode = (string) ($line->xpath('.//ram:DesignatedProductClassification/ram:ClassCode[@listID="SN"]')[0] ?? '');
            $ausgabe = $classCode !== '' ? str_pad(substr($classCode, -4), 4, '0', STR_PAD_LEFT) : null;

            $ekNetto = (float) ($line->xpath('.//ram:NetPriceProductTradePrice/ram:ChargeAmount')[0] ?? 0);
            $menge = (int) round((float) ($line->xpath('.//ram:BilledQuantity')[0] ?? 0));
            $mwstSatz = (float) ($line->xpath('.//ram:ApplicableTradeTax/ram:RateApplicablePercent')[0] ?? 0);
            $gesamtNetto = (float) ($line->xpath('.//ram:LineTotalAmount')[0] ?? 0);

            $positions[] = [
                'typ' => $isLieferung ? 'lieferung' : 'remission',
                'lieferschein_nr' => $lieferscheinNr,
                'paket' => $isRemission ? ($lieferscheinNr ?? 'REMI') : null,
                'lieferschein_datum' => $datum,
                'objekt' => $objekt,
                'bezeichnung' => $bezeichnung,
                'weekday' => $weekday,
                'ean' => $ean,
                'ausgabe' => $ausgabe,
                'menge' => $menge,
                'einzelpreis_netto' => $ekNetto,
                'einzelpreis_brutto' => $fixedPrice,
                'mwst_satz' => $mwstSatz,
                'gesamt_netto' => $gesamtNetto,
            ];
        }

        return $positions;
    }

    /**
     * Wochentag aus Bezeichnung extrahieren.
     * Beispiele: "BILD Bund 1Mo" -> 1, "Welt am Sonntag 7So" -> 7
     * Auch ohne Ziffer: "Bezeichnung Mo" -> 1, "... So" -> 7
     */
    private function extractWeekday(string $bezeichnung): ?int
    {
        // Mit Ziffer: "1Mo", "2Di" usw.
        if (preg_match('/\b([1-7])(Mo|Di|Mi|Do|Fr|Sa|So)\b/u', $bezeichnung, $m)) {
            return (int) $m[1];
        }

        // Tag-Suffix ohne Ziffer
        $map = ['Mo' => 1, 'Di' => 2, 'Mi' => 3, 'Do' => 4, 'Fr' => 5, 'Sa' => 6, 'So' => 7];
        if (preg_match('/\b(Mo|Di|Mi|Do|Fr|Sa|So)\b\s*$/u', $bezeichnung, $m)) {
            return $map[$m[1]] ?? null;
        }

        // Volle Wochentagsnamen
        $full = ['Montag' => 1, 'Dienstag' => 2, 'Mittwoch' => 3, 'Donnerstag' => 4, 'Freitag' => 5, 'Samstag' => 6, 'Sonntag' => 7];
        foreach ($full as $name => $num) {
            if (stripos($bezeichnung, $name) !== false) return $num;
        }

        return null;
    }

    /**
     * Eine Position verarbeiten: Artikel + OrderLine speichern.
     */
    private function processPosition(array $pos, Invoice $invoice, string $tenantId, array &$stats): void
    {
        $eanInfo = $this->eanInspector->inspect($pos['ean']);
        $vkpBrutto = $pos['einzelpreis_brutto'] ?? ($eanInfo['preis_brutto'] ?? 0);
        $mwstSatz = $pos['mwst_satz'] > 0 ? $pos['mwst_satz'] : ($eanInfo['mwst_satz'] ?? 0);
        $vkpNetto = $vkpBrutto > 0 && $mwstSatz > 0
            ? round($vkpBrutto / (1 + $mwstSatz / 100), 4)
            : 0;

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
                'weekday' => $pos['weekday'] ?? null,
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
                'ean' => $pos['ean'] ?: $article->ean,
                'bezeichnung' => $pos['bezeichnung'] ?: $article->bezeichnung,
                'weekday' => $pos['weekday'] ?? $article->weekday,
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

        if ($pos['ausgabe']) {
            ArticleIssue::firstOrCreate(
                ['article_id' => $article->id, 'ausgabe' => $pos['ausgabe']],
                ['first_seen_at' => now(), 'last_seen_at' => now()],
            )->update(['last_seen_at' => now()]);
        }

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
            'gesamt_brutto' => $mwstSatz > 0
                ? round($pos['gesamt_netto'] * (1 + $mwstSatz / 100), 4)
                : $pos['gesamt_netto'],
        ]);
    }
}
