<?php

namespace App\Console\Commands;

use App\Models\CoolingSetting;
use App\Models\CoolingUnit;
use App\Services\MobileAlertsClient;
use Illuminate\Console\Command;

/**
 * Diagnose: zeigt die Roh-Antwort der Mobile-Alerts-API fuer die angelegten
 * Kuehlmoebel — zum Finden von phoneid/Sensor-ID/Kanal-Problemen.
 */
class TestTemperatures extends Command
{
    protected $signature = 'temperatures:test';

    protected $description = 'Diagnose-Abruf der Mobile-Alerts-API (Roh-Antwort anzeigen)';

    public function handle(MobileAlertsClient $client): int
    {
        $units = CoolingUnit::query()->withoutGlobalScopes()->where('is_active', true)->get();
        $this->info('Aktive Kühlmöbel: ' . $units->count());

        if ($units->isEmpty()) {
            $this->warn('Kein aktives Kühlmöbel mit device_id angelegt!');
            return self::SUCCESS;
        }

        $settings = CoolingSetting::query()->withoutGlobalScopes()->get();

        foreach ($units as $u) {
            $setting = $settings->first(fn ($s) => $s->tenant_id === $u->tenant_id && $s->station_id === $u->station_id)
                ?? $settings->first(fn ($s) => $s->tenant_id === $u->tenant_id && $s->station_id === null);
            $phoneId = $u->phoneid ?: $setting?->phoneid;
            $baseUrl = $setting?->baseUrlOrDefault() ?? CoolingSetting::DEFAULT_BASE_URL;

            $this->line('');
            $this->line("── {$u->name} (#{$u->device_number}) ──");
            $this->line("  device_id : {$u->device_id}");
            $this->line("  Kanal     : {$u->channel}");
            $this->line("  phoneid   : " . ($phoneId ?: '(keine!)'));
            $this->line("  Soll      : {$u->target_min} .. {$u->target_max}");

            $res = $client->lastMeasurement([$u->device_id], $phoneId, $baseUrl);
            if (! ($res['success'] ?? false)) {
                $this->error('  API-Fehler: ' . ($res['error'] ?? '?') . ' (Status ' . ($res['status'] ?? '-') . ')');
                continue;
            }

            $devices = $res['devices'] ?? [];
            if (empty($devices)) {
                $this->warn('  API ok, aber KEIN Gerät zurückgegeben — Sensor-ID gehört nicht zu dieser phoneid?');
                continue;
            }

            foreach ($devices as $d) {
                $m = $d['measurement'] ?? [];
                $this->line('  Antwort   : deviceid=' . ($d['deviceid'] ?? '?')
                    . ' lastseen=' . (isset($d['lastseen']) ? date('d.m.Y H:i', (int) $d['lastseen']) : '?')
                    . ' lowbat=' . (($d['lowbat'] ?? false) ? 'ja' : 'nein'));
                $this->line('  Messwerte : ' . json_encode($m, JSON_UNESCAPED_UNICODE));
                [$val] = $client->readChannel($m, $u->channel);
                $this->line('  -> Kanal ' . $u->channel . ' = ' . ($val ?? 'null'));
            }
        }

        return self::SUCCESS;
    }
}
