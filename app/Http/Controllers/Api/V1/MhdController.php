<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Device;
use App\Models\Mhd;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MhdController extends ApiController
{
    /**
     * GET /api/v1/mhd?device_token=xxx
     *
     * Alle aktiven MHD-Eintraege der Station, sortiert nach Restlaufzeit.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string',
        ]);

        $device = $this->findDevice($request->device_token);
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $entries = Mhd::forStation($device->station_id)
            ->notDisposed()
            ->oldestFirst()
            ->get();

        $data = $entries->map(fn (Mhd $mhd) => $this->formatEntry($mhd));

        return $this->success($data->toArray(), $entries->count() . ' MHD-Eintraege gefunden.');
    }

    /**
     * GET /api/v1/mhd/summary?device_token=xxx
     *
     * Zusammenfassung: Anzahl abgelaufen, Warnung, OK.
     * warning_days kann per Query-Parameter ueberschrieben werden (Default: 7).
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string',
            'warning_days' => 'nullable|integer|min:1',
        ]);

        $device = $this->findDevice($request->device_token);
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $warningDays = (int) ($request->warning_days ?? 7);

        $entries = Mhd::forStation($device->station_id)
            ->notDisposed()
            ->get();

        $today = Carbon::today();
        $expired = 0;
        $warning = 0;
        $ok = 0;

        foreach ($entries as $entry) {
            $remaining = $entry->remaining_days;
            if ($remaining < 0) {
                $expired++;
            } elseif ($remaining <= $warningDays) {
                $warning++;
            } else {
                $ok++;
            }
        }

        return $this->success([
            'total_count' => $entries->count(),
            'expired_count' => $expired,
            'warning_count' => $warning,
            'ok_count' => $ok,
        ], 'MHD-Zusammenfassung.');
    }

    /**
     * POST /api/v1/mhd
     *
     * Neuen MHD-Eintrag erstellen.
     * Duplikat-Pruefung ueber kenner (MD5 aus station_id + tms_no + mhd_date).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string',
            'tms_no' => 'required|integer',
            'article_description' => 'required|string|max:255',
            'mhd_date' => 'required|date|after:today',
            'ean' => 'nullable|string|max:50',
            'supplier' => 'nullable|string|max:255',
            'delivery_date' => 'nullable|date',
            'recorded_by' => 'nullable|string|max:255',
        ]);

        $device = $this->findDevice($request->device_token);
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $stationId = $device->station_id;
        $kenner = Mhd::generateKenner($stationId, (int) $request->tms_no, $request->mhd_date);

        // Duplikat-Pruefung: Aktiver Eintrag mit gleichem kenner?
        $existing = Mhd::forStation($stationId)
            ->where('kenner', $kenner)
            ->first();

        if ($existing) {
            return $this->error('Artikel mit diesem MHD bereits erfasst.', 409);
        }

        $mhd = Mhd::create([
            'station_id' => $stationId,
            'date_recorded' => Carbon::today()->toDateString(),
            'recorded_by' => $request->recorded_by,
            'tms_no' => $request->tms_no,
            'ean' => $request->ean,
            'article_description' => $request->article_description,
            'mhd_date' => $request->mhd_date,
            'kenner' => $kenner,
            'supplier' => $request->supplier,
            'delivery_date' => $request->delivery_date,
            'goods_disposed' => false,
        ]);

        return $this->success($this->formatEntry($mhd), 'MHD-Eintrag erstellt.', 201);
    }

    /**
     * PUT /api/v1/mhd/{id}/extend
     *
     * MHD-Datum verlaengern (neues Datum setzen).
     * Kenner wird neu berechnet, Duplikat-Pruefung erfolgt.
     */
    public function extend(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string',
            'mhd_date' => 'required|date|after:today',
        ]);

        $device = $this->findDevice($request->device_token);
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $mhd = Mhd::forStation($device->station_id)->find($id);
        if (! $mhd) {
            return $this->error('MHD-Eintrag nicht gefunden.', 404);
        }

        // Neuen Kenner berechnen und auf Duplikat pruefen
        $newKenner = Mhd::generateKenner($device->station_id, $mhd->tms_no, $request->mhd_date);
        $existing = Mhd::forStation($device->station_id)
            ->where('kenner', $newKenner)
            ->where('id', '!=', $id)
            ->first();

        if ($existing) {
            return $this->error('Ein Eintrag mit diesem neuen MHD-Datum existiert bereits.', 409);
        }

        $mhd->update([
            'mhd_date' => $request->mhd_date,
            'kenner' => $newKenner,
        ]);

        return $this->success($this->formatEntry($mhd->fresh()), 'MHD-Datum verlaengert.');
    }

    /**
     * POST /api/v1/mhd/{id}/dispose
     *
     * Artikel abschreiben (goods_disposed = true).
     */
    public function dispose(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string',
            'disposed_quantity' => 'nullable|integer|min:1',
        ]);

        $device = $this->findDevice($request->device_token);
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $mhd = Mhd::forStation($device->station_id)->find($id);
        if (! $mhd) {
            return $this->error('MHD-Eintrag nicht gefunden.', 404);
        }

        $mhd->update([
            'goods_disposed' => true,
            'disposed_quantity' => $request->disposed_quantity,
        ]);

        return $this->success(null, 'Artikel abgeschrieben.');
    }

    /**
     * DELETE /api/v1/mhd/{id}?device_token=xxx
     *
     * MHD-Eintrag soft-deleten (erledigt).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string',
        ]);

        $device = $this->findDevice($request->device_token);
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $mhd = Mhd::forStation($device->station_id)->find($id);
        if (! $mhd) {
            return $this->error('MHD-Eintrag nicht gefunden.', 404);
        }

        $mhd->delete();

        return $this->success(null, 'MHD-Eintrag geloescht.');
    }

    // -- Helpers --

    /**
     * MHD-Eintrag fuer API-Response formatieren.
     */
    private function formatEntry(Mhd $mhd): array
    {
        return [
            'id' => $mhd->id,
            'station_id' => $mhd->station_id,
            'date_recorded' => $mhd->date_recorded->toDateString(),
            'recorded_by' => $mhd->recorded_by,
            'tms_no' => $mhd->tms_no,
            'ean' => $mhd->ean,
            'article_description' => $mhd->article_description,
            'supplier' => $mhd->supplier,
            'delivery_date' => $mhd->delivery_date?->toDateString(),
            'mhd_date' => $mhd->mhd_date->toDateString(),
            'goods_disposed' => (int) $mhd->goods_disposed,
            'disposed_quantity' => $mhd->disposed_quantity,
            'remaining_days' => $mhd->remaining_days,
        ];
    }

    /**
     * Geraet anhand des Device-Tokens finden.
     */
    private function findDevice(string $plainToken): ?Device
    {
        return Device::findByPlainToken($plainToken);
    }
}
