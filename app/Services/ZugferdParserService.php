<?php

namespace App\Services;

use App\Models\CorporateCustomer;
use App\Models\GasStation;
use DOMDocument;
use DOMXPath;

/**
 * ZUGFeRD XML-Parser Service (adaptiert von TMS_Invoicing).
 *
 * Extrahiert und verarbeitet ZUGFeRD-XML-Daten aus PDF-Rechnungen.
 * - XML-Extraktion aus PDF (Binary-Suche)
 * - Parsing von Verkaeufer/Kaeufer-Daten
 * - Rechnungspositionen
 * - Tankstellen-Matching via Adresse
 * - Dateinamen-Parsing
 */
class ZugferdParserService
{
    /**
     * Extrahiert ZUGFeRD XML aus PDF.
     * Sucht nach CrossIndustryInvoice-Patterns im PDF-Binary.
     */
    public function extractXmlFromPdf(string $pdfPath): ?string
    {
        if (! file_exists($pdfPath)) {
            return null;
        }

        $content = file_get_contents($pdfPath);

        $patterns = [
            '/<rsm:CrossIndustryInvoice.*?<\/rsm:CrossIndustryInvoice>/s',
            '/<CrossIndustryInvoice.*?<\/CrossIndustryInvoice>/s',
            '/<?xml version.*?<rsm:CrossIndustryInvoice.*?<\/rsm:CrossIndustryInvoice>/s',
            '/<\?xml.*?<\/rsm:CrossIndustryInvoice>/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return $matches[0];
            }
        }

        // Zusaetzliche Suche in komprimierten Streams
        if (function_exists('gzuncompress') && preg_match('/stream\s*(.*?)\s*endstream/s', $content, $streamMatches)) {
            $decompressed = @gzuncompress($streamMatches[1]);
            if ($decompressed !== false) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $decompressed, $matches)) {
                        return $matches[0];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Parst ZUGFeRD-Daten aus PDF.
     * Extrahiert Verkaeufer, Kaeufer, Rechnung und Positionen.
     */
    public function parseZugferdData(string $pdfPath): ?array
    {
        $xml = $this->extractXmlFromPdf($pdfPath);

        if (! $xml) {
            return null;
        }

        try {
            $dom = new DOMDocument();
            @$dom->loadXML($xml);
            $xpath = new DOMXPath($dom);
            $this->registerNamespaces($xpath);

            $data = [
                // Verkaeufer (Tankstelle)
                'seller_name' => $this->getXPathValue($xpath, '//ram:SellerTradeParty//ram:Name'),
                'seller_street' => $this->getXPathValue($xpath, '//ram:SellerTradeParty//ram:LineOne'),
                'seller_postal' => $this->getXPathValue($xpath, '//ram:SellerTradeParty//ram:PostcodeCode'),
                'seller_city' => $this->getXPathValue($xpath, '//ram:SellerTradeParty//ram:CityName'),
                'seller_email' => $this->getXPathValue($xpath, '//ram:SellerTradeParty//ram:URIID[@schemeID="EM"]'),
                'seller_phone' => $this->getXPathValue($xpath, '//ram:SellerTradeParty//ram:CompleteNumber'),

                // Kaeufer (Firmenkunde)
                'buyer_id' => $this->getXPathValue($xpath, '//ram:BuyerTradeParty//ram:ID'),
                'buyer_reference' => $this->getXPathValue($xpath, '//ram:BuyerReference'),
                'buyer_name' => $this->getXPathValue($xpath, '//ram:BuyerTradeParty//ram:Name'),
                'buyer_street' => $this->getXPathValue($xpath, '//ram:BuyerTradeParty//ram:LineOne'),
                'buyer_postal' => $this->getXPathValue($xpath, '//ram:BuyerTradeParty//ram:PostcodeCode'),
                'buyer_city' => $this->getXPathValue($xpath, '//ram:BuyerTradeParty//ram:CityName'),
                'buyer_email' => $this->getXPathValue($xpath, '//ram:BuyerTradeParty//ram:URIID[@schemeID="EM"]'),

                // Rechnung
                'invoice_number' => $this->getXPathValue($xpath, '//rsm:ExchangedDocument//ram:ID'),
                'invoice_date' => $this->parseZugferdDate($this->getXPathValue($xpath, '//rsm:ExchangedDocument//ram:IssueDateTime//udt:DateTimeString')),
                'total_amount' => (float) $this->getXPathValue($xpath, '//ram:GrandTotalAmount'),
                'tax_amount' => (float) $this->getXPathValue($xpath, '//ram:TaxTotalAmount'),
                'net_amount' => (float) $this->getXPathValue($xpath, '//ram:LineTotalAmount'),

                // Positionen
                'items' => $this->parseLineItems($xpath),
            ];

            // Tankstelle automatisch finden
            $station = $this->findStationByAddress($data);
            if ($station) {
                $data['station_id'] = $station->id;
                $data['station'] = $station;
            }

            return $data;
        } catch (\Exception $e) {
            \Log::error('ZUGFeRD Parsing Error: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Registriert XML-Namespaces fuer XPath.
     */
    private function registerNamespaces(DOMXPath $xpath): void
    {
        $namespaces = [
            'rsm' => 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100',
            'ram' => 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100',
            'udt' => 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100',
        ];

        foreach ($namespaces as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }
    }

    /**
     * Parst Rechnungspositionen aus XML.
     */
    private function parseLineItems(DOMXPath $xpath): array
    {
        $items = [];
        $itemNodes = $xpath->query('//ram:IncludedSupplyChainTradeLineItem');

        foreach ($itemNodes as $node) {
            $items[] = [
                'name' => $this->getXPathValue($xpath, './/ram:Name', $node),
                'quantity' => (float) $this->getXPathValue($xpath, './/ram:BilledQuantity', $node),
                'unit_price' => (float) $this->getXPathValue($xpath, './/ram:ChargeAmount', $node),
                'line_total' => (float) $this->getXPathValue($xpath, './/ram:LineTotalAmount', $node),
            ];
        }

        return $items;
    }

    /**
     * XPath-Wert sicher auslesen.
     */
    private function getXPathValue(DOMXPath $xpath, string $query, $contextNode = null): ?string
    {
        $nodes = $xpath->query($query, $contextNode);

        return $nodes->length > 0 ? trim($nodes->item(0)->nodeValue) : null;
    }

    /**
     * ZUGFeRD-Datumsformat parsen (102 = YYYYMMDD).
     */
    private function parseZugferdDate(?string $dateString): ?\Carbon\Carbon
    {
        if (! $dateString) {
            return null;
        }

        if (preg_match('/^\d{8}$/', $dateString)) {
            try {
                return \Carbon\Carbon::createFromFormat('Ymd', $dateString)->startOfDay();
            } catch (\Exception $e) {
                // Fallback
            }
        }

        try {
            return \Carbon\Carbon::parse($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Findet Tankstelle anhand Adressdaten aus ZUGFeRD.
     * Sucht ueber PLZ + Stadt + Strassenname.
     */
    public function findStationByAddress(array $data): ?GasStation
    {
        $postal = $data['seller_postal'] ?? null;
        $city = $data['seller_city'] ?? null;
        $street = $data['seller_street'] ?? null;

        if (! $postal || ! $city) {
            return null;
        }

        // Suche ueber PLZ + Stadt
        $query = GasStation::where('zip', $postal)
            ->where('city', 'LIKE', '%' . $city . '%');

        // Wenn Strasse vorhanden, auch danach filtern
        if ($street) {
            $streetName = $this->extractStreetName($street);
            $query->where('street', 'LIKE', '%' . $streetName . '%');
        }

        $results = $query->get();

        if ($results->count() > 1) {
            \Illuminate\Support\Facades\Log::warning('ZUGFeRD: Mehrere Tankstellen fuer Adresse gefunden', [
                'plz' => $postal,
                'stadt' => $city,
                'strasse' => $street,
                'treffer' => $results->count(),
            ]);
        }

        return $results->first();
    }

    /**
     * Findet oder erstellt Firmenkunden aus ZUGFeRD-Daten.
     *
     * Kundennummer-Prioritaet:
     * 1. buyer_reference (aus ZUGFeRD-XML)
     * 2. filenameCustomerNumber (aus Dateiname)
     * 3. buyer_id als Fallback
     */
    public function findOrCreateCustomer(array $zugferdData, ?string $filenameCustomerNumber, string $gasStationId): ?CorporateCustomer
    {
        $customerNumber = $zugferdData['buyer_reference']
            ?? $filenameCustomerNumber
            ?? $zugferdData['buyer_id']
            ?? null;

        if (! $customerNumber) {
            return null;
        }

        // Bestehenden Kunden suchen
        $customer = CorporateCustomer::where('gas_station_id', $gasStationId)
            ->where('customer_number', (int) $customerNumber)
            ->first();

        if ($customer) {
            // Ergaenzende Felder aus ZUGFeRD updaten (Merge-Logik)
            $updates = [];

            // Name nur updaten wenn Platzhalter ("Kunde 123")
            $buyerName = $zugferdData['buyer_name'] ?? null;
            if ($buyerName && preg_match('/^Kunde\s+\d+$/', $customer->name)) {
                $updates['name'] = $buyerName;
            }

            // Email nur updaten wenn aktuell Placeholder und ZUGFeRD echte Email liefert
            $buyerEmail = $zugferdData['buyer_email'] ?? null;
            if ($buyerEmail && $customer->email === CorporateCustomer::PLACEHOLDER_EMAIL) {
                $updates['email'] = $buyerEmail;
            }

            // Adresse nur ergaenzen wenn leer
            if (empty($customer->street) && ! empty($zugferdData['buyer_street'])) {
                $updates['street'] = $zugferdData['buyer_street'];
            }
            if (empty($customer->zip) && ! empty($zugferdData['buyer_postal'])) {
                $updates['zip'] = $zugferdData['buyer_postal'];
            }
            if (empty($customer->city) && ! empty($zugferdData['buyer_city'])) {
                $updates['city'] = $zugferdData['buyer_city'];
            }

            if (! empty($updates)) {
                $customer->update($updates);
                \Log::info('ZUGFeRD Merge: Kundendaten ergaenzt', [
                    'kunde' => $customer->customer_number,
                    'felder' => array_keys($updates),
                ]);
            }

            return $customer;
        }

        // Neuen Kunden anlegen
        try {
            return CorporateCustomer::create([
                'gas_station_id' => $gasStationId,
                'customer_number' => (int) $customerNumber,
                'name' => $zugferdData['buyer_name'] ?? 'Kunde ' . $customerNumber,
                'email' => $zugferdData['buyer_email'] ?? \App\Models\InvoiceSetting::get('default_customer_email', \App\Models\CorporateCustomer::PLACEHOLDER_EMAIL),
                'street' => $zugferdData['buyer_street'] ?? null,
                'zip' => $zugferdData['buyer_postal'] ?? null,
                'city' => $zugferdData['buyer_city'] ?? null,
                'send_via_email' => false,
                'send_via_print' => true,
                'is_active' => true,
            ]);
        } catch (\Exception $e) {
            \Log::error('Fehler beim Anlegen des Kunden', [
                'error' => $e->getMessage(),
                'customer_number' => $customerNumber,
            ]);

            return null;
        }
    }

    /**
     * Extrahiert Daten aus Dateinamen.
     * Format: 002188_600000_ARAL-Tankstelle.pdf
     */
    public function extractFromFilename(string $filename): array
    {
        $filename = str_replace('.pdf', '', basename($filename));
        $parts = explode('_', $filename);
        $nameParts = array_slice($parts, 2);

        return [
            'invoice_number' => $parts[0] ?? null,
            'customer_number' => $parts[1] ?? null,
            'name' => ! empty($nameParts) ? implode(' ', $nameParts) : null,
        ];
    }

    /**
     * Baut strukturierten Speicherpfad fuer PDF.
     * Format: invoices/{Jahr}/{Monat_deutsch}/{StationsNr}/{KdNr}_{KdName}/{Dateiname}
     */
    public static function buildStoragePath($invoiceDate, ?string $stationNumber, ?string $customerNumber, ?string $customerName, string $originalFilename): string
    {
        $months = [1 => 'Januar', 2 => 'Februar', 3 => 'Maerz', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'];

        $date = null;
        if ($invoiceDate instanceof \Carbon\Carbon) {
            $date = $invoiceDate;
        } elseif (is_string($invoiceDate) && preg_match('/^\d{8}$/', $invoiceDate)) {
            try {
                $date = \Carbon\Carbon::createFromFormat('Ymd', $invoiceDate);
            } catch (\Exception $e) {
            }
        }
        if (! $date || $date->year < 2000) {
            $date = now();
        }

        $year = $date->format('Y');
        $month = $months[$date->month] ?? 'Unbekannt';
        $station = self::safeName($stationNumber ?: 'Ohne_Tankstelle');
        $customer = self::safeName(($customerNumber ?: '0') . '_' . ($customerName ?: 'Unbekannt'));
        $filename = self::safeName(pathinfo($originalFilename, PATHINFO_FILENAME)) . '.pdf';

        return "invoices/{$year}/{$month}/{$station}/{$customer}/{$filename}";
    }

    /**
     * Bereinigt Namen fuer Ordner/Dateien.
     */
    public static function safeName(string $name): string
    {
        $name = str_replace(['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'], ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'], $name);
        $name = preg_replace('/[^\w\-]/', '_', $name);
        $name = preg_replace('/_+/', '_', trim($name, '_'));
        $name = rtrim($name, '.');

        return substr($name, 0, 100) ?: 'Unbekannt';
    }

    /**
     * Validiert ob PDF ZUGFeRD-Daten enthaelt.
     */
    public function hasZugferdData(string $pdfPath): bool
    {
        return $this->extractXmlFromPdf($pdfPath) !== null;
    }

    /**
     * Extrahiert Strassenname ohne Hausnummer und normalisiert Abkuerzungen.
     * "Hauptstr. 12a" → "Hauptstr"
     * "Am Marktplatz 3" → "Am Marktplatz"
     * "A 7" → "A 7" (Autobahn, keine Hausnummer)
     */
    private function extractStreetName(string $street): string
    {
        $street = trim($street);

        // Hausnummer am Ende entfernen (Zahl + optionaler Buchstabe)
        $withoutNumber = preg_replace('/\s+\d+\s*[a-zA-Z]?\s*$/', '', $street);

        // Nur entfernen wenn noch etwas uebrig bleibt (z.B. "A 7" behalten)
        if (! empty($withoutNumber) && strlen($withoutNumber) > 1) {
            $street = $withoutNumber;
        }

        // Abkuerzungen normalisieren
        $street = preg_replace('/\bStra(?:ss|ß)e\b/i', 'Str', $street);
        $street = preg_replace('/\bStr\.\b/i', 'Str', $street);

        return trim($street);
    }
}
