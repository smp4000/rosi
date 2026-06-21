<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CoolingAlert;
use App\Models\CoolingReading;
use App\Models\CoolingUnit;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * App-API fuer das Temperatur-Management: aktuelle Werte (Gauges),
 * Verlauf (Diagramm) und Stoerungen je Station des Geraets.
 */
class TemperatureController extends ApiController
{
    /**
     * GET /api/v1/temperatures/units?device_token=...
     * Aktuelle Werte aller Kuehlmoebel der Station (fuer die Gauges).
     */
    public function units(Request $request): JsonResponse
    {
        $device = Device::findByPlainToken($request->query('device_token', ''));
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $units = CoolingUnit::query()
            ->where('station_id', $device->station_id)
            ->where('is_active', true)
            ->orderBy('device_number')
            ->orderBy('sort')
            ->orderBy('name')
            ->withCount(['activeAlerts'])
            ->get();

        return $this->success([
            'units' => $units->map(fn (CoolingUnit $u) => $this->formatUnit($u))->values(),
        ]);
    }

    /**
     * GET /api/v1/temperatures/units/{id}/history?device_token=...&hours=24
     * Messwerte fuer das Verlaufsdiagramm.
     */
    public function history(Request $request, string $id): JsonResponse
    {
        $device = Device::findByPlainToken($request->query('device_token', ''));
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $unit = CoolingUnit::query()
            ->where('station_id', $device->station_id)
            ->where('id', $id)
            ->first();
        if (! $unit) {
            return $this->error('Kühlmöbel nicht gefunden.', 404);
        }

        $hours = (int) $request->query('hours', 24);
        $hours = max(1, min($hours, 24 * 31)); // 1h .. 31 Tage

        $readings = CoolingReading::query()
            ->where('cooling_unit_id', $unit->id)
            ->whereNotNull('value')
            ->where('measured_at', '>=', now()->subHours($hours))
            ->orderBy('measured_at')
            ->get(['value', 'humidity', 'measured_at']);

        return $this->success([
            'unit' => $this->formatUnit($unit),
            'points' => $readings->map(fn (CoolingReading $r) => [
                't' => $r->measured_at?->getTimestamp(),
                'v' => (float) $r->value,
                'h' => $r->humidity !== null ? (float) $r->humidity : null,
            ])->values(),
            'min' => $readings->min('value') !== null ? (float) $readings->min('value') : null,
            'max' => $readings->max('value') !== null ? (float) $readings->max('value') : null,
        ]);
    }

    /**
     * GET /api/v1/temperatures/alerts?device_token=...&status=active
     * Stoerungen der Station.
     */
    public function alerts(Request $request): JsonResponse
    {
        $device = Device::findByPlainToken($request->query('device_token', ''));
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $status = $request->query('status', 'active');

        $alerts = CoolingAlert::query()
            ->where('station_id', $device->station_id)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with('unit:id,name,device_number')
            ->orderByDesc('started_at')
            ->limit(100)
            ->get();

        return $this->success([
            'alerts' => $alerts->map(fn (CoolingAlert $a) => [
                'id' => $a->id,
                'unit_name' => $a->unit?->name,
                'device_number' => $a->unit?->device_number,
                'type' => $a->type,
                'type_label' => $a->type_label,
                'value' => $a->value !== null ? (float) $a->value : null,
                'threshold' => $a->threshold !== null ? (float) $a->threshold : null,
                'status' => $a->status,
                'started_at' => $a->started_at?->getTimestamp(),
                'ended_at' => $a->ended_at?->getTimestamp(),
                'acknowledged' => $a->acknowledged_at !== null,
            ])->values(),
        ]);
    }

    private function formatUnit(CoolingUnit $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'device_number' => $u->device_number,
            'type' => $u->type,
            'type_label' => CoolingUnit::TYPE_LABELS[$u->type] ?? $u->type,
            'category' => $u->category,
            'value' => $u->last_value !== null ? (float) $u->last_value : null,
            'humidity' => $u->last_humidity !== null ? (float) $u->last_humidity : null,
            'status' => $u->last_status,                       // ok|high|low|offline|null
            'target_min' => $u->target_min !== null ? (float) $u->target_min : null,
            'target_max' => $u->target_max !== null ? (float) $u->target_max : null,
            'track_humidity' => (bool) $u->track_humidity,
            'lowbat' => (bool) $u->last_lowbat,
            'last_reading_at' => $u->last_reading_at?->getTimestamp(),
            'active_alerts' => (int) ($u->active_alerts_count ?? 0),
        ];
    }
}
