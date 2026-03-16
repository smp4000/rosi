<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service zum Importieren von Tankstellen-Daten von benzinpreis-aktuell.de.
 * Verwendet Nominatim fuer PLZ→Stadt-Aufloesung und die PLZ-basierte Umkreissuche.
 */
class BenzinpreisService
{
    private const BASE_URL = 'https://www.benzinpreis-aktuell.de/';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Sucht Tankstellen im 20km-Umkreis einer PLZ.
     * Gibt Array von ['hash', 'slug', 'name', 'street', 'city'] zurueck.
     */
    public function searchByPlz(string $plz): array
    {
        if (strlen($plz) !== 5 || ! ctype_digit($plz)) {
            return [];
        }

        return Cache::remember("bp_plz20_{$plz}", now()->addMinutes(15), function () use ($plz) {
            // 1. Ortsnamen aus PLZ via Nominatim
            $place = $this->resolvePlace($plz);
            if (! $place) {
                return [];
            }

            // 2. PLZ-Umkreissuche: {plz}-{city}-aktuelle-e10preise?umkreis=20
            // Stadtname muss exakt zur PLZ passen, daher Fallback-Kette
            $candidates = [$place['city'], ...$place['fallbacks']];
            $result = null;

            foreach ($candidates as $city) {
                $slug = $this->cityToSlug($city);
                $result = $this->fetchPlzSearchPage($plz, $slug);
                if ($result && ! empty($result['stations'])) {
                    break;
                }
                $result = null;
            }

            if (! $result) {
                return [];
            }

            // Alphabetisch nach Name sortieren
            $stations = array_values($result['stations']);
            usort($stations, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

            return $stations;
        });
    }

    /**
     * Laedt und parst die Detail-Seite einer Tankstation.
     * Gibt assoziatives Array mit allen verfuegbaren Daten zurueck.
     */
    public function fetchStationDetails(string $hash, string $slug): ?array
    {
        $cacheKey = "bp_station_{$hash}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($hash, $slug) {
            $url = self::BASE_URL . "preise-t{$hash}-{$slug}";

            try {
                $response = Http::withoutVerifying()->timeout(12)
                    ->withUserAgent(self::USER_AGENT)
                    ->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9'])
                    ->get($url);

                if (! $response->successful()) {
                    Log::warning("BenzinpreisService: HTTP {$response->status()} fuer {$url}");

                    return null;
                }

                return $this->parseStationPage($response->body());
            } catch (\Exception $e) {
                Log::error("BenzinpreisService: Fehler beim Laden von {$url}: {$e->getMessage()}");

                return null;
            }
        });
    }

    /**
     * Loest PLZ in Stadtnamen auf via Nominatim (OpenStreetMap).
     * Gibt ['city' => string, 'fallbacks' => string[]] zurueck.
     * Fallbacks sind alternative Ortsnamen (Gemeinde, Landkreis) falls der Hauptort zu klein ist.
     */
    private function resolvePlace(string $plz): ?array
    {
        try {
            $response = Http::withoutVerifying()->timeout(5)
                ->withUserAgent(self::USER_AGENT . ' (ROSI-App)')
                ->get('https://nominatim.openstreetmap.org/search', [
                    'postalcode' => $plz,
                    'country' => 'de',
                    'format' => 'json',
                    'limit' => 1,
                    'addressdetails' => 1,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            if (empty($data)) {
                return null;
            }

            $address = $data[0]['address'] ?? [];

            // Hauptstadt: city > town > village
            $city = $address['city'] ?? $address['town'] ?? $address['village'] ?? null;

            // Fallback-Kette: municipality, dann Landkreis-Stadt (fuer kleine Ortsteile)
            $fallbacks = [];
            if (! empty($address['municipality']) && $address['municipality'] !== $city) {
                $fallbacks[] = $address['municipality'];
            }
            // "Landkreis Fulda" → "Fulda"
            $county = $address['county'] ?? '';
            if ($county && preg_match('/^(?:Landkreis|Kreis)\s+(.+)$/i', $county, $cm)) {
                $countyCity = trim($cm[1]);
                if ($countyCity !== $city && ! in_array($countyCity, $fallbacks)) {
                    $fallbacks[] = $countyCity;
                }
            }

            if ($city) {
                return ['city' => $city, 'fallbacks' => $fallbacks];
            }

            // Fallback: aus display_name parsen
            $displayName = $data[0]['display_name'] ?? '';
            $parts = explode(',', $displayName);
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part && ! is_numeric($part) && strlen($part) > 2 && $part !== 'Deutschland') {
                    return ['city' => $part, 'fallbacks' => $fallbacks];
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error("BenzinpreisService: Nominatim-Fehler: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Laedt die PLZ-basierte Umkreissuche und extrahiert Stationen.
     * URL: {plz}-{citySlug}-aktuelle-e10preise?umkreis=20
     * Gibt ['stations' => [...]] zurueck, null bei 404.
     */
    private function fetchPlzSearchPage(string $plz, string $citySlug): ?array
    {
        $url = self::BASE_URL . "{$plz}-{$citySlug}-aktuelle-e10preise?umkreis=20";

        try {
            $response = Http::withoutVerifying()->timeout(15)
                ->withUserAgent(self::USER_AGENT)
                ->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9'])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            $html = $response->body();

            // 404-Seite erkennen (HTTP 200 aber Titel "401 - Seite leider nicht gefunden")
            if (preg_match('/<title>[^<]*(?:nicht gefunden|404|401)/i', $html)) {
                return null;
            }

            return $this->parseStationBlocks($html);
        } catch (\Exception $e) {
            Log::error("BenzinpreisService: PLZ-Suche Fehler fuer {$plz}-{$citySlug}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Parst Stationsblocks aus HTML.
     * Gleiche Block-Struktur auf Stadt- und PLZ-Suchseiten:
     * <div id="station-tHASH-SLUG"> ... <strong>NAME</strong><br>STRASSE ...
     */
    private function parseStationBlocks(string $html): array
    {
        $stations = [];

        if (preg_match_all(
            '/<div id="station-t([a-f0-9]+)-([^"]+)"[^>]*>(.+?)(?=<div id="station-t|<div class="sxi"|$)/s',
            $html,
            $blocks,
            PREG_SET_ORDER
        )) {
            foreach ($blocks as $block) {
                $hash = $block[1];
                $slug = rtrim($block[2], '/');
                $blockHtml = $block[3];

                if (isset($stations[$hash])) {
                    continue;
                }

                // Name + Strasse aus dem Block
                if (! preg_match('/<strong class="isstrong">([^<]+)<\/strong><br>\s*([^<]+)/s', $blockHtml, $nm)) {
                    continue;
                }

                $name = trim(html_entity_decode($nm[1], ENT_QUOTES, 'UTF-8'));
                $street = trim(html_entity_decode($nm[2], ENT_QUOTES, 'UTF-8'));

                if (! $name) {
                    continue;
                }

                // Name muss "Tankstelle" enthalten (filtert Beschreibungstexte)
                if (! preg_match('/Tankstelle/i', $name)) {
                    continue;
                }

                // Stadt aus Stationsname extrahieren ("ESSO Tankstelle Fulda" → "Fulda")
                $city = '';
                if (preg_match('/Tankstelle\s+(.+)$/i', $name, $cm)) {
                    $city = trim($cm[1]);
                }

                // Preis aus em-Tag extrahieren (z.B. <em title="...">1.92</em><sup>9</sup>)
                $price = null;
                $fuelType = '';
                if (preg_match('/<em\s+title="([^"]*)">([\d.]+)<\/em><sup>(\d)<\/sup>/i', $blockHtml, $pm)) {
                    $price = $pm[2] . $pm[3]; // z.B. "1.929"
                    // Spritsorte aus title extrahieren ("E10-Preis", "Diesel-Preis", "Super-Preis")
                    if (preg_match('/(E10|Diesel|Super\s*E5|Super\s*Plus|Super)\s*-?\s*Preis/i', $pm[1], $ft)) {
                        $fuelType = trim($ft[1]);
                    }
                }

                $stations[$hash] = [
                    'hash' => $hash,
                    'slug' => $slug,
                    'name' => $name,
                    'street' => $street,
                    'city' => $city,
                    'price' => $price,
                    'fuel_type' => $fuelType,
                ];
            }
        }

        return ['stations' => $stations];
    }

    /**
     * Parst eine Tankstellen-Detailseite und extrahiert alle Daten.
     */
    private function parseStationPage(string $html): ?array
    {
        $result = [
            'name' => '',
            'brand' => '',
            'street' => '',
            'house_number' => '',
            'zip' => '',
            'city' => '',
            'lat' => null,
            'lng' => null,
            'opening_hours' => [],
            'prices' => [],
        ];

        // Marke/Brand aus Titel
        if (preg_match('/Spritpreise\s+(\S+)\s+in\s+(\S+)/i', $html, $tm)) {
            $result['brand'] = strip_tags(trim($tm[1]));
            $result['city'] = rtrim(strip_tags(trim($tm[2])), ':.,;');
        }

        // Adresse: Strategie 1 - Suffix-basiert (Strasse, Weg, Platz etc.)
        $streetSuffix = 'stra(?:ß|ss)e|str\.|weg|platz|gasse|berg|ring|allee|damm|chaussee|hof|steig';
        if (preg_match(
            '/([A-ZÄÖÜa-zäöüß][^<>]{0,80}?(?:' . $streetSuffix . '))\s+(\d[\d\s\-\/a-z]*?)\s*<br>\s*(\d{5})\s+([A-ZÄÖÜa-zäöüß][^<>]{0,60}?)\s*</iu',
            $html,
            $am
        )) {
            $result['street'] = trim($am[1]);
            $result['house_number'] = trim($am[2]);
            $result['zip'] = trim($am[3]);
            $result['city'] = trim($am[4]);
        } elseif (preg_match(
            '/([A-ZÄÖÜa-zäöüß][^<>]{0,80}?(?:' . $streetSuffix . '))\s*<br>\s*(\d{5})\s+([A-ZÄÖÜa-zäöüß][^<>]{0,60}?)\s*</iu',
            $html,
            $am
        )) {
            $result['street'] = trim($am[1]);
            $result['zip'] = trim($am[2]);
            $result['city'] = trim($am[3]);
        }

        // Adresse: Strategie 2 - "Wo finde ich die Tankstelle?" Section (fuer Strassen ohne Standard-Suffix)
        if (! $result['street'] && preg_match(
            '/Wo finde ich die Tankstelle\?\s*<\/h2>\s*<p[^>]*>\s*(.+?)\s+(\d[\d\s\-\/a-z]*?)\s*<br>\s*(\d{5})\s+([^<]+)/iu',
            $html,
            $am
        )) {
            $result['street'] = trim($am[1]);
            $result['house_number'] = trim($am[2]);
            $result['zip'] = trim($am[3]);
            $result['city'] = trim($am[4]);
        }

        // Adresse: Strategie 3 - "Wo finde ich" ohne Hausnummer
        if (! $result['street'] && preg_match(
            '/Wo finde ich die Tankstelle\?\s*<\/h2>\s*<p[^>]*>\s*(.+?)\s*<br>\s*(\d{5})\s+([^<]+)/iu',
            $html,
            $am
        )) {
            $result['street'] = trim($am[1]);
            $result['zip'] = trim($am[2]);
            $result['city'] = trim($am[3]);
        }

        // Adresse: Strategie 4 - Google Maps Link (daddr=...+PLZ+Stadt)
        if (! $result['street'] && preg_match('/daddr=([^&"\']+)/i', $html, $gm)) {
            // Raw-Value VOR urldecode am + splitten (urldecode wandelt + in Leerzeichen)
            $parts = explode('+', $gm[1]);
            $parts = array_map(fn ($p) => urldecode(trim($p)), $parts);
            $parts = array_values(array_filter($parts, fn ($p) => $p !== ''));

            foreach ($parts as $i => $part) {
                if (preg_match('/^\d{5}$/', $part)) {
                    // Alles vor PLZ = Strasse (ohne Markenname/Tankstelle)
                    $streetParts = array_slice($parts, 0, $i);
                    // "ARAL Tankstelle Fulda" Prefix entfernen
                    $streetStr = implode(' ', $streetParts);
                    if (preg_match('/(?:Tankstelle\s+\S+\s+)(.+)$/i', $streetStr, $sp)) {
                        $streetStr = $sp[1];
                    }
                    $result['street'] = trim($streetStr);
                    $result['zip'] = $part;
                    $result['city'] = trim(implode(' ', array_slice($parts, $i + 1)));
                    break;
                }
            }

            // Hausnummer aus Strasse extrahieren (letzte Zahl am Ende)
            if ($result['street'] && preg_match('/^(.+?)\s+(\d[\d\s\-\/a-z]*)$/iu', $result['street'], $hn)) {
                $result['street'] = trim($hn[1]);
                $result['house_number'] = trim($hn[2]);
            }
        }

        // Koordinaten aus Meta-Tags
        if (preg_match('/property="place:location:latitude"\s+content="([\d.]+)"/i', $html, $latM)) {
            $result['lat'] = round((float) $latM[1], 8);
        }
        if (preg_match('/property="place:location:longitude"\s+content="([\d.]+)"/i', $html, $lngM)) {
            $result['lng'] = round((float) $lngM[1], 8);
        }

        // Oeffnungszeiten
        $dayMap = [
            'Montag' => 'monday', 'Dienstag' => 'tuesday', 'Mittwoch' => 'wednesday',
            'Donnerstag' => 'thursday', 'Freitag' => 'friday',
            'Samstag' => 'saturday', 'Sonntag' => 'sunday',
        ];

        if (preg_match_all(
            '/<p\s+class="e-otimes[^"]*">\s*<em>([^<]+)<\/em>\s*<span>([^<]+)<\/span>/i',
            $html,
            $hm,
            PREG_SET_ORDER
        )) {
            foreach ($hm as $h) {
                $time = trim($h[1]);
                $dayDe = trim($h[2]);
                $dayEn = $dayMap[$dayDe] ?? null;

                if ($dayEn && preg_match('/(\d{2}:\d{2})\s*bis\s*(\d{2}:\d{2})/', $time, $tp)) {
                    $result['opening_hours'][$dayEn] = [
                        'open' => $tp[1],
                        'close' => $tp[2],
                    ];
                }
            }
        }

        // Name aus <title>
        if (preg_match('/<title>\s*([^|<]+)/i', $html, $titleM)) {
            $fullTitle = trim($titleM[1]);
            if (preg_match('/^(\S+)\s+(?:Tankstelle|Preise)\s+(\S+)/i', $fullTitle, $ntm)) {
                $result['name'] = rtrim(trim($ntm[1]), ':.,') . ' ' . rtrim(trim($ntm[2]), ':.,');
            }
        }

        // Fallback Name
        if (! $result['name']) {
            $brand = $result['brand'] ?: '';
            $city = $result['city'] ?: '';
            $result['name'] = trim("{$brand} {$city}");
        }

        // --- Preise extrahieren ---

        // Strategie 1: Preise aus div.preise25 (Hauptanzeige, am zuverlaessigsten)
        // Format: <div class="preis25 preis_e10"><em>1,97<sup>9</sup></em><p>Super E10
        if (preg_match_all(
            '/<div[^>]*class="[^"]*preis25[^"]*preis_(\w+)[^"]*"[^>]*>\s*<em>([\d,]+)<sup>(\d)<\/sup><\/em>/si',
            $html,
            $pm,
            PREG_SET_ORDER
        )) {
            foreach ($pm as $p) {
                $type = strtolower(trim($p[1]));
                $price = str_replace(',', '.', $p[2]) . $p[3]; // "1,97" + "9" -> "1.979"
                $fuelName = match ($type) {
                    'benzin' => 'Super',
                    'e10' => 'E10',
                    'diesel' => 'Diesel',
                    default => ucfirst($type),
                };
                $result['prices'][$fuelName] = $price;
            }
        }

        // Strategie 2: Vergleichstabelle (Kraftstoff | Bundesschnitt | Region | Station)
        if (empty($result['prices']) && preg_match('/<table\s+class="stbl">(.*?)<\/table>/si', $html, $tableM)) {
            $tableHtml = $tableM[1];
            $fuelCols = [];
            if (preg_match('/<thead>(.*?)<\/thead>/si', $tableHtml, $headM)) {
                if (preg_match_all('/<th[^>]*>([^<]+)/i', $headM[1], $ths)) {
                    $fuelCols = array_map('trim', $ths[1]);
                }
            }

            // Format A: Zeilen = Kraftstoffarten, letzte Spalte = Stationspreis
            if (in_array('Kraftstoff', $fuelCols)) {
                if (preg_match_all('/<tr>\s*<td>(\w+)<\/td>(.*?)<\/tr>/si', $tableHtml, $rows, PREG_SET_ORDER)) {
                    foreach ($rows as $row) {
                        $fuelName = match (strtolower(trim($row[1]))) {
                            'super', 'benzin' => 'Super',
                            'e10' => 'E10',
                            'diesel' => 'Diesel',
                            default => trim($row[1]),
                        };
                        // Letzten Preiswert nehmen (= Stationspreis)
                        if (preg_match_all('/([\d.,]+)\s*€/i', $row[2], $vals)) {
                            $lastPrice = end($vals[1]);
                            $result['prices'][$fuelName] = str_replace(',', '.', $lastPrice);
                        }
                    }
                }
            }
            // Format B: Spalten = Kraftstoffarten, Zeilen = Tage (Heute/Jetzt)
            else {
                if (preg_match('/<tr>\s*<td>.*?Heute.*?<\/td>(.*?)<\/tr>/si', $tableHtml, $rowM)) {
                    if (preg_match_all('/<td>([\d.,]+)\s*€<\/td>/i', $rowM[1], $vals)) {
                        foreach ($vals[1] as $i => $val) {
                            $colIdx = $i + 1;
                            $fuelName = $fuelCols[$colIdx] ?? '';
                            $fuelName = match (strtolower(trim($fuelName))) {
                                'benzin' => 'Super',
                                'e10' => 'E10',
                                'diesel' => 'Diesel',
                                default => trim($fuelName),
                            };
                            if ($fuelName) {
                                $result['prices'][$fuelName] = str_replace(',', '.', $val);
                            }
                        }
                    }
                }
            }
        }

        // Stadt bereinigen (Trailing Satzzeichen entfernen)
        $result['city'] = rtrim($result['city'], ':.,;');

        // Name mit Strasse ergaenzen
        if ($result['name'] && $result['street']) {
            $result['name'] .= ' · ' . $result['street'];
            if ($result['house_number']) {
                $result['name'] .= ' ' . $result['house_number'];
            }
        }

        return $result;
    }

    /**
     * Konvertiert Stadtnamen in URL-Slug (Umlaute, Sonderzeichen).
     */
    private function cityToSlug(string $city): string
    {
        $slug = mb_strtolower(trim($city));
        $slug = str_replace(
            ['ä', 'ö', 'ü', 'ß', 'é', 'è', 'ê', 'á', 'à'],
            ['ae', 'oe', 'ue', 'ss', 'e', 'e', 'e', 'a', 'a'],
            $slug
        );
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-');
    }
}
