<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AddressLabel;
use App\Models\Device;
use App\Models\GasStation;
use App\Models\LabelTemplate;
use App\Services\PrintQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Adress-Etiketten (Brief-Etiketten) fuer den Partner-Bereich der App.
 *
 * Oeffentlich (device_token):  GET stations, GET index, GET search
 * Geschuetzt (auth:sanctum):   POST store (anlegen + drucken), POST {id}/print
 */
class AddressLabelController extends ApiController
{
    /** GET /address-labels/stations — eigene Stationen als Absender-Auswahl. */
    public function stations(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        if (! $tenantId) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $stations = GasStation::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get()
            ->map(fn (GasStation $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'sender_text' => $this->senderText($s),
            ])
            ->values();

        return $this->success(['stations' => $stations]);
    }

    /** GET /address-labels — gespeicherte Etiketten (Tenant), neueste zuerst. */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        if (! $tenantId) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $labels = AddressLabel::where('tenant_id', $tenantId)
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->map(fn (AddressLabel $l) => $this->labelData($l))
            ->values();

        return $this->success(['labels' => $labels]);
    }

    /** GET /address-labels/search?q=... — Adress-Autovervollstaendigung (Nominatim). */
    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:3|max:120']);

        if (! $this->resolveTenantId($request)) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $results = [];
        try {
            $resp = Http::withHeaders(['User-Agent' => 'ROSI-App/1.0 (rosi.aral-welle.com)'])
                ->timeout(8)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $request->q,
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'countrycodes' => 'de',
                    'limit' => 6,
                ]);

            if ($resp->successful()) {
                foreach ($resp->json() as $item) {
                    $a = $item['address'] ?? [];
                    $street = trim(($a['road'] ?? '') . ' ' . ($a['house_number'] ?? ''));
                    $city = trim(($a['postcode'] ?? '') . ' '
                        . ($a['city'] ?? $a['town'] ?? $a['village'] ?? $a['municipality'] ?? ''));
                    if ($street === '' && $city === '') {
                        continue;
                    }
                    $results[] = [
                        'display' => $item['display_name'] ?? trim($street . ', ' . $city),
                        'street' => $street,
                        'city' => $city,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Adress-Suche fehlgeschlagen: ' . $e->getMessage());
        }

        return $this->success(['results' => $results]);
    }

    /** POST /address-labels — Etikett anlegen + drucken (auth:sanctum). */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_token' => 'required|string',
            'sender_station_id' => 'required|uuid',
            'recipient_name' => 'required|string|max:255',
            'recipient_street' => 'nullable|string|max:255',
            'recipient_city' => 'nullable|string|max:255',
            'target_agent_id' => 'nullable|uuid',
            'printer_name' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        if (! $user) {
            return $this->error('Nicht angemeldet.', 401);
        }

        $device = Device::findByPlainToken($validated['device_token']);
        $station = $device ? GasStation::find($device->station_id) : null;
        if (! $station) {
            return $this->error('Tankstelle nicht gefunden.', 404);
        }

        $sender = GasStation::where('tenant_id', $station->tenant_id)
            ->find($validated['sender_station_id']);
        if (! $sender) {
            return $this->error('Absender-Station nicht gefunden.', 404);
        }

        $label = AddressLabel::create([
            'tenant_id' => $station->tenant_id,
            'sender_station_id' => $sender->id,
            'sender_text' => $this->senderText($sender),
            'recipient_name' => $validated['recipient_name'],
            'recipient_street' => $validated['recipient_street'] ?? null,
            'recipient_city' => $validated['recipient_city'] ?? null,
            'print_count' => 0,
            'created_by' => $user->name,
        ]);

        $error = $this->printLabel($station, $label, $validated, $user->name, $user->id);
        if ($error) {
            return $this->error($error, 422);
        }

        $label->increment('print_count');
        $label->update(['last_printed_at' => now()]);

        return $this->success([
            'label' => $this->labelData($label->fresh()),
        ], 'Adress-Etikett gespeichert und gedruckt.');
    }

    /** POST /address-labels/{id}/print — gespeichertes Etikett erneut drucken. */
    public function reprint(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'device_token' => 'required|string',
            'target_agent_id' => 'nullable|uuid',
            'printer_name' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        if (! $user) {
            return $this->error('Nicht angemeldet.', 401);
        }

        $device = Device::findByPlainToken($validated['device_token']);
        $station = $device ? GasStation::find($device->station_id) : null;
        if (! $station) {
            return $this->error('Tankstelle nicht gefunden.', 404);
        }

        $label = AddressLabel::where('tenant_id', $station->tenant_id)->find($id);
        if (! $label) {
            return $this->error('Etikett nicht gefunden.', 404);
        }

        $error = $this->printLabel($station, $label, $validated, $user->name, $user->id);
        if ($error) {
            return $this->error($error, 422);
        }

        $label->increment('print_count');
        $label->update(['last_printed_at' => now()]);

        return $this->success([
            'label' => $this->labelData($label->fresh()),
        ], 'Adress-Etikett erneut gedruckt.');
    }

    // ── Helfer ───────────────────────────────────────────

    /** Etikett rendern und in die Druck-Queue legen. Gibt Fehlertext oder null. */
    private function printLabel(GasStation $station, AddressLabel $label, array $opts, string $userName, string $userId): ?string
    {
        $template = LabelTemplate::findForTenant('adresse', $station->tenant_id);
        if (! $template) {
            return 'Keine Adress-Druckvorlage vorhanden.';
        }

        $xml = $template->render([
            'name' => $label->recipient_name,
            'strasse' => $label->recipient_street ?? '',
            'ort' => $label->recipient_city ?? '',
            'absender' => $label->sender_text,
        ]);

        try {
            app(PrintQueueService::class)->enqueue($station, 'address_label', [
                ['number' => 'ADR', 'xml' => $xml],
            ], [
                'reference' => $label->recipient_name,
                'reference_type' => 'address_label',
                'created_by' => $userName,
                'user_id' => $userId,
                'target_agent_id' => $opts['target_agent_id'] ?? null,
                'printer_name' => $opts['printer_name'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Adress-Etikett Queue fehlgeschlagen: ' . $e->getMessage());
            return 'Druck fehlgeschlagen: ' . $e->getMessage();
        }

        return null;
    }

    /** Absender-Zeile aus einer Station bauen. */
    private function senderText(GasStation $s): string
    {
        $street = trim(($s->street ?? '') . ' ' . ($s->house_number ?? ''));
        $city = trim(($s->zip ?? '') . ' ' . ($s->city ?? ''));

        return collect([$s->name, $street, $city])
            ->filter(fn ($p) => trim((string) $p) !== '')
            ->implode(', ');
    }

    private function labelData(AddressLabel $l): array
    {
        return [
            'id' => $l->id,
            'sender_text' => $l->sender_text,
            'recipient_name' => $l->recipient_name,
            'recipient_street' => $l->recipient_street,
            'recipient_city' => $l->recipient_city,
            'print_count' => $l->print_count,
            'last_printed_at' => $l->last_printed_at?->toIso8601String(),
        ];
    }

    private function resolveTenantId(Request $request): ?string
    {
        $token = $request->query('device_token', $request->input('device_token', ''));
        return Device::findByPlainToken($token)?->tenant_id;
    }
}
