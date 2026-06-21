<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client fuer die Mobile Alerts REST API (data199.com).
 * Liefert die jeweils letzte Messung der Funksensoren.
 *
 * Doku: POST {base}/device/lastmeasurement
 *   deviceids = kommagetrennte Sensor-IDs, phoneid optional (fuer Alert-Flags).
 * Rate-Limit: 3 Abrufe/Sensor/Minute (sonst HTTP 429, 7 Min Sperre).
 */
class MobileAlertsClient
{
    /** Sensor war nicht verbunden. */
    public const VALUE_DISCONNECTED = 43530;
    /** Messwert ausserhalb des Messbereichs. */
    public const VALUE_OUT_OF_RANGE = 65295;

    /**
     * Letzte Messungen mehrerer Sensoren abrufen.
     *
     * @param  string[]  $deviceIds
     * @return array{success:bool, devices?:array, error?:string, status?:int}
     */
    public function lastMeasurement(array $deviceIds, ?string $phoneId = null, ?string $baseUrl = null): array
    {
        $deviceIds = array_values(array_unique(array_filter($deviceIds)));
        if (empty($deviceIds)) {
            return ['success' => false, 'error' => 'Keine Sensor-IDs'];
        }

        $base = rtrim($baseUrl ?: \App\Models\CoolingSetting::DEFAULT_BASE_URL, '/');
        $payload = ['deviceids' => implode(',', $deviceIds)];
        if (! empty($phoneId)) {
            $payload['phoneid'] = $phoneId;
        }

        try {
            // XAMPP/lokal: SSL nicht verifizieren (siehe Projekt-Konvention).
            $response = Http::withoutVerifying()
                ->asForm()
                ->timeout(20)
                ->post($base . '/device/lastmeasurement', $payload);

            if ($response->status() === 429) {
                return ['success' => false, 'error' => 'Rate-Limit (429)', 'status' => 429];
            }
            if ($response->status() === 403) {
                return ['success' => false, 'error' => 'Gesperrt (403)', 'status' => 403];
            }
            if (! $response->successful()) {
                return ['success' => false, 'error' => 'HTTP ' . $response->status(), 'status' => $response->status()];
            }

            $json = $response->json();
            if (! is_array($json) || ($json['success'] ?? false) !== true) {
                return [
                    'success' => false,
                    'error' => $json['errormessage'] ?? 'Unerwartete Antwort',
                ];
            }

            return ['success' => true, 'devices' => $json['devices'] ?? []];
        } catch (\Throwable $e) {
            Log::warning('MobileAlerts API Fehler', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Liest den Temperaturwert eines gewuenschten Kanals (t1/t2) aus einer Messung.
     * Liefert [value|null, disconnected, outOfRange].
     *
     * @return array{0: ?float, 1: bool, 2: bool}
     */
    public function readChannel(array $measurement, string $channel): array
    {
        $raw = $measurement[$channel] ?? null;
        if ($raw === null) {
            return [null, false, false];
        }
        $val = (float) $raw;
        if ((int) round($val) === self::VALUE_DISCONNECTED) {
            return [null, true, false];
        }
        if ((int) round($val) === self::VALUE_OUT_OF_RANGE) {
            return [null, false, true];
        }

        return [$val, false, false];
    }
}
