<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service zum Importieren von Tankstellen-Daten von benzinpreis-aktuell.de.
 * Verwendet Nominatim fuer PLZ→Stadt-Aufloesung und parst die Stationsseiten.
 * Sucht im 25km-Umkreis ueber verlinkte Nachbarstaedte.
 */
class BenzinpreisService
{
    private const BASE_URL = 'https://www.benzinpreis-aktuell.de/';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Sucht Tankstellen im 25km-Umkreis einer PLZ.
     * Gibt Array von ['hash', 'slug', 'name', 'street', 'city'] zurueck.
     */
    public function searchByPlz(string $plz): array
    {
        if (strlen($plz) !== 5 || ! ctype_digit($plz)) {
            return [];
        }

        return Cache::remember("bp_search25_{$plz}", now()->addMinutes(15), function () use ($plz) {
            // 1. Stadt + Koordinaten aus PLZ via Nominatim
            $place = $this->resolvePlace($plz);
            if (! $place) {
                return [];
            }

            $originLat = $place['lat'];
            $originLng = $place['lng'];

            // 2. Hauptstadt-Seite laden → Stationen + Nachbarstaedte
            // Bei kleinen Orten (Ortsteile) Fallback auf Gemeinde/Kreisstadt
            $mainSlug = $this->cityToSlug($place['city']);
            $mainResult = $this->fetchCityPage($mainSlug);

            // Fallback: Wenn Hauptort keine Ergebnisse liefert, Gemeinde/Kreisstadt versuchen
            if (! $mainResult || empty($mainResult['stations'])) {
                foreach ($place['fallbacks'] as $fallbackCity) {
                    $fallbackSlug = $this->cityToSlug($fallbackCity);
                    $mainResult = $this->fetchCityPage($fallbackSlug);
                    if ($mainResult && ! empty($mainResult['stations'])) {
                        // Pruefen ob die Seite geografisch passt (gleichnamige Staedte in anderem Bundesland)
                        if ($this->validateCityPageLocation($mainResult, $originLat, $originLng)) {
                            $mainSlug = $fallbackSlug;
                            break;
                        }
                        $mainResult = null; // Falsche Stadt, naechsten Fallback probieren
                    }
                }
            }

            // Auch Hauptergebnis validieren (z.B. "Petersberg" existiert in Hessen + Sachsen-Anhalt)
            if ($mainResult && ! empty($mainResult['stations'])) {
                if (! $this->validateCityPageLocation($mainResult, $originLat, $originLng)) {
                    // Falsche Stadt - Fallbacks versuchen
                    $mainResult = null;
                    foreach ($place['fallbacks'] as $fallbackCity) {
                        $fallbackSlug = $this->cityToSlug($fallbackCity);
                        $mainResult = $this->fetchCityPage($fallbackSlug);
                        if ($mainResult && ! empty($mainResult['stations']) &&
                            $this->validateCityPageLocation($mainResult, $originLat, $originLng)) {
                            $mainSlug = $fallbackSlug;
                            break;
                        }
                        $mainResult = null;
                    }
                }
            }

            if (! $mainResult) {
                return [];
            }

            $allStations = $mainResult['stations'];
            $nearbySlugs = $mainResult['nearby_slugs'];

            // 3. Nachbarstadt-Seiten parallel laden (Level 1)
            // Hauptstadt-Slug rausfiltern (ist schon geladen)
            $nearbySlugs = array_values(array_filter($nearbySlugs, fn ($s) => $s !== $mainSlug));

            if (! empty($nearbySlugs)) {
                $nearbyStations = $this->fetchMultipleCityPages($nearbySlugs);

                foreach ($nearbyStations as $slug => $result) {
                    // Geografische Validierung: gleichnamige Staedte in anderem Bundesland filtern
                    if (! $this->validateCityPageLocation($result, $originLat, $originLng)) {
                        continue;
                    }

                    foreach ($result['stations'] as $hash => $station) {
                        if (! isset($allStations[$hash])) {
                            $allStations[$hash] = $station;
                        }
                    }
                }
            }

            // Alphabetisch nach Name sortieren
            $result = array_values($allStations);
            usort($result, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

            return $result;
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
     * Loest PLZ in Stadtnamen und Koordinaten auf via Nominatim (OpenStreetMap).
     * Gibt ['city' => string, 'fallbacks' => string[], 'lat' => float, 'lng' => float] zurueck.
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

            $lat = (float) ($data[0]['lat'] ?? 0);
            $lng = (float) ($data[0]['lon'] ?? 0);
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
                return ['city' => $city, 'fallbacks' => $fallbacks, 'lat' => $lat, 'lng' => $lng];
            }

            // Fallback: aus display_name parsen
            $displayName = $data[0]['display_name'] ?? '';
            $parts = explode(',', $displayName);
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part && ! is_numeric($part) && strlen($part) > 2 && $part !== 'Deutschland') {
                    return ['city' => $part, 'fallbacks' => $fallbacks, 'lat' => $lat, 'lng' => $lng];
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error("BenzinpreisService: Nominatim-Fehler: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Laedt eine Stadt-Seite und extrahiert Stationen + Nachbarstaedte.
     * Gibt ['stations' => [...], 'nearby_slugs' => [...]] zurueck.
     */
    private function fetchCityPage(string $citySlug): ?array
    {
        $url = self::BASE_URL . "benzinpreise-{$citySlug}-heute";

        try {
            $response = Http::withoutVerifying()->timeout(12)
                ->withUserAgent(self::USER_AGENT)
                ->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9'])
                ->get($url);

            if (! $response->successful()) {
                return null;
            }

            return $this->parseCityPage($response->body());
        } catch (\Exception $e) {
            Log::error("BenzinpreisService: City-Fehler fuer {$citySlug}: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Laedt mehrere Stadt-Seiten parallel via Http::pool.
     */
    private function fetchMultipleCityPages(array $citySlugs): array
    {
        $results = [];

        $responses = Http::pool(function ($pool) use ($citySlugs) {
            foreach ($citySlugs as $slug) {
                $pool->as($slug)->withoutVerifying()->timeout(12)
                    ->withUserAgent(self::USER_AGENT)
                    ->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9'])
                    ->get(self::BASE_URL . "benzinpreise-{$slug}-heute");
            }
        });

        foreach ($responses as $slug => $response) {
            if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
                $parsed = $this->parseCityPage($response->body());
                if ($parsed) {
                    $results[$slug] = $parsed;
                }
            }
        }

        return $results;
    }

    /**
     * Parst eine Stadt-Seite: extrahiert Tankstellen (mit Adresse) und Nachbarstadt-Links.
     * HTML-Struktur: <strong class="isstrong">NAME</strong><br>STRASSE<span...>
     * gefolgt von: <a href="preise-tHASH-SLUG">
     */
    private function parseCityPage(string $html): array
    {
        $stations = [];
        $nearbySlugs = [];

        // Jede Station ist ein <div id="station-tHASH-SLUG" class="ns_newsquare..."> Block.
        // Wir splitten nach diesen Bloecken um Cross-Block-Matches zu vermeiden.
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

                $stations[$hash] = [
                    'hash' => $hash,
                    'slug' => $slug,
                    'name' => $name,
                    'street' => $street,
                    'city' => $city,
                ];
            }
        }

        // Nachbarstadt-Links: href="...benzinpreise-SLUG-heute..."
        if (preg_match_all('/href=["\'][^"\']*benzinpreise-([a-z][a-z0-9\-]+)-heute[^"\']*["\']/i', $html, $cityMatches)) {
            $nearbySlugs = array_unique($cityMatches[1]);
        }

        return [
            'stations' => $stations,
            'nearby_slugs' => $nearbySlugs,
        ];
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
        ];

        // Marke/Brand aus Titel
        if (preg_match('/Spritpreise\s+(\S+)\s+in\s+(\S+)/i', $html, $tm)) {
            $result['brand'] = strip_tags(trim($tm[1]));
            $result['city'] = strip_tags(trim($tm[2]));
        }

        // Adresse: Flexibles Pattern fuer Strassennamen mit Suffix
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

        // Fallback: Google Maps Link (daddr=Strasse+Nr+PLZ+Stadt)
        if (! $result['street'] && preg_match('/daddr=([^&"\']+)/i', $html, $gm)) {
            $addr = urldecode($gm[1]);
            $parts = preg_split('/\+/', $addr);
            foreach ($parts as $i => $part) {
                if (preg_match('/^\d{5}$/', trim($part))) {
                    $result['street'] = trim(implode(' ', array_slice($parts, 0, $i)));
                    $result['zip'] = trim($part);
                    $result['city'] = trim(implode(' ', array_slice($parts, $i + 1)));
                    break;
                }
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
                $result['name'] = trim($ntm[1]) . ' ' . trim($ntm[2]);
            }
        }

        // Fallback Name
        if (! $result['name']) {
            $brand = $result['brand'] ?: '';
            $city = $result['city'] ?: '';
            $result['name'] = trim("{$brand} {$city}");
        }

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
     * Validiert ob eine Stadtseite geografisch zur gesuchten PLZ passt.
     * Laedt die Detailseite der ersten Station und prueft die Koordinaten-Entfernung.
     */
    private function validateCityPageLocation(array $cityResult, float $originLat, float $originLng): bool
    {
        if (empty($cityResult['stations'])) {
            return false;
        }

        // Erste Station mit Hash/Slug nehmen und Detail-Seite laden
        $firstStation = reset($cityResult['stations']);
        $details = $this->fetchStationDetails($firstStation['hash'], $firstStation['slug']);

        if (! $details || ! $details['lat'] || ! $details['lng']) {
            // Keine Koordinaten verfuegbar - optimistisch annehmen
            return true;
        }

        // Entfernung berechnen (Haversine, vereinfacht)
        $distance = $this->haversineDistance($originLat, $originLng, $details['lat'], $details['lng']);

        // Maximal 50km Toleranz
        return $distance <= 50;
    }

    /**
     * Berechnet die Entfernung zwischen zwei Koordinaten in km (Haversine-Formel).
     */
    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371; // Erdradius in km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
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
