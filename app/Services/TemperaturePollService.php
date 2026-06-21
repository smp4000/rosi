<?php

namespace App\Services;

use App\Models\CoolingAlert;
use App\Models\CoolingReading;
use App\Models\CoolingSetting;
use App\Models\CoolingUnit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Holt regelmaessig die letzten Messungen aller aktiven Kuehlmoebel von der
 * Mobile-Alerts-API, speichert die Historie und oeffnet/schliesst Alarme
 * anhand der ROSI-eigenen Soll-Grenzwerte.
 *
 * Laeuft im CLI (Server-Cron) -> der TenantScope ist dort inaktiv, daher werden
 * alle Mandanten erfasst. tenant_id wird beim Speichern explizit gesetzt.
 */
class TemperaturePollService
{
    public function __construct(
        private MobileAlertsClient $client,
        private FcmService $fcm,
    ) {}

    /**
     * @return array{units:int, readings:int, errors:int}
     */
    public function poll(): array
    {
        $stats = ['units' => 0, 'readings' => 0, 'errors' => 0];

        $units = CoolingUnit::query()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->whereNotNull('device_id')
            ->get();

        if ($units->isEmpty()) {
            return $stats;
        }

        $settings = CoolingSetting::query()->withoutGlobalScopes()->get();

        // Gruppieren nach (baseUrl | phoneid), damit Sensor-IDs gebuendelt abgefragt werden.
        $groups = [];
        foreach ($units as $unit) {
            $setting = $this->resolveSetting($settings, $unit);
            if ($setting && ! $setting->enabled) {
                continue;
            }
            $baseUrl = $setting?->baseUrlOrDefault() ?? CoolingSetting::DEFAULT_BASE_URL;
            $phoneId = $unit->phoneid ?: $setting?->phoneid;
            $key = $baseUrl . '|' . ($phoneId ?? '');
            $groups[$key]['baseUrl'] = $baseUrl;
            $groups[$key]['phoneId'] = $phoneId;
            $groups[$key]['units'][] = $unit;
        }

        foreach ($groups as $group) {
            /** @var CoolingUnit[] $groupUnits */
            $groupUnits = $group['units'];
            $deviceIds = array_values(array_unique(array_map(fn ($u) => $u->device_id, $groupUnits)));

            $result = $this->client->lastMeasurement($deviceIds, $group['phoneId'], $group['baseUrl']);
            if (! ($result['success'] ?? false)) {
                $stats['errors']++;
                Log::warning('Temperatur-Poll fehlgeschlagen', ['error' => $result['error'] ?? '?']);
                $this->notifyError($groupUnits, $result['error'] ?? 'Unbekannter Fehler');
                continue;
            }

            // Messungen nach Sensor-ID indexieren.
            $byDevice = [];
            foreach (($result['devices'] ?? []) as $dev) {
                $byDevice[strtoupper($dev['deviceid'] ?? '')] = $dev;
            }

            foreach ($groupUnits as $unit) {
                $stats['units']++;
                $dev = $byDevice[strtoupper($unit->device_id)] ?? null;
                if ($this->handleUnit($unit, $dev)) {
                    $stats['readings']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Verarbeitet eine Sensor-Antwort fuer ein Kuehlmoebel: speichert die Messung
     * und aktualisiert Alarme. Liefert true, wenn eine Messung gespeichert wurde.
     */
    private function handleUnit(CoolingUnit $unit, ?array $dev): bool
    {
        // Sensor nicht in der Antwort -> evtl. offline pruefen.
        if (! $dev) {
            $this->checkOffline($unit);
            return false;
        }

        $lastSeen = isset($dev['lastseen']) ? Carbon::createFromTimestamp((int) $dev['lastseen']) : null;
        $lowbat = (bool) ($dev['lowbat'] ?? false);
        $m = $dev['measurement'] ?? [];

        [$value, $disconnected, $outOfRange] = $this->client->readChannel($m, $unit->channel);
        $humidity = isset($m['h']) ? (float) $m['h'] : null;
        $measuredAt = isset($m['ts']) ? Carbon::createFromTimestamp((int) $m['ts']) : ($lastSeen ?? now());
        $receivedAt = isset($m['c']) ? Carbon::createFromTimestamp((int) $m['c']) : null;
        $mobileIdx = isset($m['idx']) ? (int) $m['idx'] : null;

        // Messung speichern (Dedup ueber idx).
        if ($value !== null || $disconnected || $outOfRange) {
            CoolingReading::query()->firstOrCreate(
                ['cooling_unit_id' => $unit->id, 'mobile_idx' => $mobileIdx],
                [
                    'tenant_id' => $unit->tenant_id,
                    'station_id' => $unit->station_id,
                    'value' => $value,
                    'humidity' => $unit->track_humidity ? $humidity : null,
                    'measured_at' => $measuredAt,
                    'received_at' => $receivedAt,
                    'out_of_range' => $outOfRange,
                    'disconnected' => $disconnected,
                ],
            );
        }

        // ── Alarme auswerten ──
        // Batterie
        if ($lowbat) {
            $this->openAlert($unit, 'lowbat', null, null);
        } else {
            $this->resolveAlert($unit, 'lowbat');
        }

        // Offline anhand lastseen
        $offline = $lastSeen === null
            || $lastSeen->lt(now()->subMinutes($unit->offline_after_min));
        if ($offline) {
            $this->openAlert($unit, 'offline', null, null);
        } else {
            $this->resolveAlert($unit, 'offline');
        }

        $status = 'ok';
        if ($value !== null) {
            $status = $unit->evaluate($value);
            if ($status === 'high') {
                $this->openAlert($unit, 'high', $value, $unit->target_max ? (float) $unit->target_max : null);
                $this->resolveAlert($unit, 'low');
            } elseif ($status === 'low') {
                $this->openAlert($unit, 'low', $value, $unit->target_min ? (float) $unit->target_min : null);
                $this->resolveAlert($unit, 'high');
            } else {
                $this->resolveAlert($unit, 'high');
                $this->resolveAlert($unit, 'low');
            }
        }

        // Letzten Stand cachen.
        $unit->forceFill([
            'last_reading_at' => $measuredAt,
            'last_value' => $value,
            'last_humidity' => $unit->track_humidity ? $humidity : null,
            'last_lowbat' => $lowbat,
            'last_status' => $offline ? 'offline' : $status,
        ])->saveQuietly();

        return $value !== null;
    }

    private function checkOffline(CoolingUnit $unit): void
    {
        $lastAt = $unit->last_reading_at;
        $offline = $lastAt === null || $lastAt->lt(now()->subMinutes($unit->offline_after_min));
        if ($offline) {
            $this->openAlert($unit, 'offline', null, null);
            $unit->forceFill(['last_status' => 'offline'])->saveQuietly();
        }
    }

    /** Findet die passende Einstellung: erst Station-spezifisch, dann Tenant-Default. */
    private function resolveSetting($settings, CoolingUnit $unit): ?CoolingSetting
    {
        return $settings->first(fn ($s) => $s->tenant_id === $unit->tenant_id && $s->station_id === $unit->station_id)
            ?? $settings->first(fn ($s) => $s->tenant_id === $unit->tenant_id && $s->station_id === null);
    }

    /** Oeffnet einen Alarm, falls noch kein aktiver desselben Typs existiert. */
    private function openAlert(CoolingUnit $unit, string $type, ?float $value, ?float $threshold): void
    {
        $exists = CoolingAlert::query()->withoutGlobalScopes()
            ->where('cooling_unit_id', $unit->id)
            ->where('type', $type)
            ->where('status', 'active')
            ->exists();
        if ($exists) {
            return;
        }

        $alert = CoolingAlert::query()->create([
            'tenant_id' => $unit->tenant_id,
            'station_id' => $unit->station_id,
            'cooling_unit_id' => $unit->id,
            'type' => $type,
            'value' => $value,
            'threshold' => $threshold,
            'status' => 'active',
            'started_at' => now(),
        ]);

        // App-Push bei NEUER Stoerung
        $this->notifyAlert($unit, $alert);
    }

    /** Push-Benachrichtigung bei neuer Stoerung an die Geraete der Station. */
    private function notifyAlert(CoolingUnit $unit, CoolingAlert $alert): void
    {
        $tokens = $this->pushTokensForStation($unit->station_id);
        if (empty($tokens)) {
            return;
        }

        $label = CoolingAlert::TYPE_LABELS[$alert->type] ?? $alert->type;
        $v = $alert->value !== null ? ' ' . number_format((float) $alert->value, 1, ',', '.') . ' °C' : '';
        $title = '⚠️ ' . $label . ': ' . $unit->name;
        $body = '#' . ($unit->device_number ?? '?') . ' ' . $unit->name . ' — ' . $label . $v;

        $sent = $this->fcm->sendToTokens($tokens, $title, $body, [
            'type' => 'cooling_alert',
            'unit_id' => (string) $unit->id,
            'alert_type' => $alert->type,
        ]);
        if ($sent > 0) {
            $alert->update(['notified_at' => now()]);
        }
    }

    /** Push bei technischem Abruf-Fehler (gedrosselt: 1x pro Stunde je Station). */
    private function notifyError(array $units, string $error): void
    {
        $stationIds = collect($units)->pluck('station_id')->unique()->filter();
        foreach ($stationIds as $stationId) {
            $cacheKey = 'temp_err_notified_' . $stationId;
            if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                continue;
            }
            $tokens = $this->pushTokensForStation($stationId);
            if (empty($tokens)) {
                continue;
            }
            $this->fcm->sendToTokens(
                $tokens,
                '⚠️ Temperatur-Abruf gestört',
                'Die Temperaturen konnten nicht abgerufen werden: ' . $error,
                ['type' => 'cooling_error'],
            );
            \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->addHour());
        }
    }

    /** Aktive FCM-Tokens aller Geraete einer Station. */
    private function pushTokensForStation(?string $stationId): array
    {
        if (! $stationId) {
            return [];
        }

        return \App\Models\Device::query()
            ->withoutGlobalScopes()
            ->where('station_id', $stationId)
            ->where('is_active', true)
            ->whereNotNull('push_token')
            ->pluck('push_token')
            ->all();
    }

    /** Schliesst aktive Alarme eines Typs. */
    private function resolveAlert(CoolingUnit $unit, string $type): void
    {
        CoolingAlert::query()->withoutGlobalScopes()
            ->where('cooling_unit_id', $unit->id)
            ->where('type', $type)
            ->where('status', 'active')
            ->update(['status' => 'resolved', 'ended_at' => now()]);
    }
}
