<?php

namespace App\Services\Kiosk;

/**
 * EAN-13 Inspector fuer deutsche Presseerzeugnisse.
 *
 * EAN-Schema:
 *  - Stelle 1-3:   Praefix → MwSt-Satz (419/439=7%, 414/434=19%)
 *  - Stelle 4-8:   VDZ-Nummer (Verlag/Objekt)
 *  - Stelle 9-12:  Bruttopreis in Cent
 *  - Stelle 13:    Pruefziffer
 */
class EanInspectorService
{
    /**
     * EAN-13 auswerten.
     *
     * @return array{is_press: bool, mwst_satz: float|null, preis_brutto: float|null, preis_netto: float|null, jugendschutz: bool, check_valid: bool, prefix: string}
     */
    public function inspect(string $ean): array
    {
        $ean = preg_replace('/\D/', '', $ean) ?? '';
        if (strlen($ean) !== 13) {
            return $this->emptyResult($ean);
        }

        $prefix = substr($ean, 0, 3);
        $mwst = match ($prefix) {
            '419', '439' => 7.0,
            '414', '434' => 19.0,
            default => null,
        };

        $isPress = in_array($prefix, ['419', '414', '439', '434'], true);
        $preisBrutto = $isPress ? intval(substr($ean, 8, 4)) / 100.0 : null;
        $preisNetto = ($preisBrutto !== null && $mwst !== null)
            ? round($preisBrutto / (1 + $mwst / 100), 4)
            : null;
        $jugendschutz = in_array($prefix, ['439', '434'], true);

        return [
            'is_press' => $isPress,
            'mwst_satz' => $mwst,
            'preis_brutto' => $preisBrutto,
            'preis_netto' => $preisNetto,
            'jugendschutz' => $jugendschutz,
            'check_valid' => $this->checkDigitValid($ean),
            'prefix' => $prefix,
        ];
    }

    /**
     * EAN-13 Pruefziffer validieren.
     */
    public function checkDigitValid(string $ean): bool
    {
        if (strlen($ean) !== 13 || ! ctype_digit($ean)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += intval($ean[$i]) * ($i % 2 === 0 ? 1 : 3);
        }
        $expected = (10 - ($sum % 10)) % 10;

        return $expected === intval($ean[12]);
    }

    /**
     * 5-stelliges EAN-Addon auswerten (bei Presse).
     *
     * Stelle 1: Wochentag (1=Mo..7=So, 0=kein Tag)
     * Stelle 2: Region (0=bundesweit)
     * Stelle 3-4: Kalenderwoche (01-53)
     * Stelle 5: Pruefziffer
     */
    public function inspectAddon(string $addon): array
    {
        $addon = preg_replace('/\D/', '', $addon) ?? '';
        if (strlen($addon) !== 5) {
            return ['weekday' => null, 'region' => null, 'kw' => null];
        }

        return [
            'weekday' => intval($addon[0]) ?: null,
            'region' => intval($addon[1]),
            'kw' => intval(substr($addon, 2, 2)),
        ];
    }

    private function emptyResult(string $ean): array
    {
        return [
            'is_press' => false,
            'mwst_satz' => null,
            'preis_brutto' => null,
            'preis_netto' => null,
            'jugendschutz' => false,
            'check_valid' => false,
            'prefix' => substr($ean, 0, 3),
        ];
    }
}
