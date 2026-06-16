<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Device;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Gutschein-API: Ausgabe, Suche, Einloesung.
 *
 * Oeffentliche Endpunkte (mit device_token):
 *   GET  /vouchers/lookup?number=4567.000
 *   POST /vouchers/check-group
 *
 * Geschuetzte Endpunkte (auth:sanctum):
 *   POST /vouchers/generate
 *   POST /vouchers/redeem
 */
class VoucherController extends ApiController
{
    // ── Oeffentlich (device_token) ──────────────────────

    /**
     * GET /api/v1/vouchers/lookup?number=4567.000&device_token=...
     *
     * Sucht einen Gutschein anhand der Nummer.
     * Wird von der App genutzt (Scanner/manuelle Eingabe).
     */
    public function lookup(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);
        if (! $tenant) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $number = trim((string) $request->query('number', ''));
        if ($number === '') {
            return $this->error('Gutscheinnummer fehlt.', 422);
        }

        $voucher = Voucher::where('tenant_id', $tenant)
            ->where('voucher_number', $number)
            ->with('redemptions')
            ->first();

        if (! $voucher) {
            return $this->error("Gutschein '{$number}' nicht gefunden.", 404);
        }

        return $this->success([
            'voucher' => $this->formatVoucher($voucher),
        ]);
    }

    /**
     * POST /api/v1/vouchers/check-group
     * { "voucher_group": "4567", "quantity": 50, "device_token": "..." }
     *
     * Prueft ob eine Gutschein-Gruppe frei ist (keine Konflikte).
     */
    public function checkGroup(Request $request): JsonResponse
    {
        $device = $this->findDevice(
            $request->query('device_token', $request->input('device_token', ''))
        );
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $data = $request->validate([
            'voucher_group' => 'required|string|max:20',
            'quantity' => 'required|integer|min:1|max:500',
        ]);

        // Station-scoped: Gleiche Gutscheinnummern duerfen an verschiedenen Stationen existieren
        $conflicts = Voucher::checkGroupConflict(
            $data['voucher_group'],
            $data['quantity'],
            $device->tenant_id,
            $device->station_id,
        );

        if ($conflicts) {
            return $this->success([
                'available' => false,
                'conflicts' => $conflicts,
                'conflict_count' => count($conflicts),
            ], 'Gutscheinnummern existieren bereits');
        }

        // Preview: welche Nummern wuerden generiert?
        $preview = [];
        for ($i = 0; $i < min($data['quantity'], 5); $i++) {
            $preview[] = sprintf('%s.%03d', $data['voucher_group'], $i);
        }
        if ($data['quantity'] > 5) {
            $preview[] = '...';
            $preview[] = sprintf('%s.%03d', $data['voucher_group'], $data['quantity'] - 1);
        }

        return $this->success([
            'available' => true,
            'preview' => $preview,
        ], 'Gutscheinnummern sind verfuegbar');
    }

    // ── Geschuetzt (auth:sanctum) ───────────────────────

    /**
     * POST /api/v1/vouchers/generate
     * {
     *   "voucher_group": "4567",
     *   "quantity": 50,
     *   "amount": 50.00,
     *   "device_token": "..."   (optional, fuer Station-Zuordnung)
     * }
     *
     * Generiert eine Gruppe von Gutscheinen und gibt sie zurueck.
     */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'voucher_group' => 'required|string|max:20',
            'quantity' => 'required|integer|min:1|max:500',
            'amount' => 'required|numeric|min:0.01|max:9999.99',
            'device_token' => 'nullable|string',
            'target_agent_id' => 'nullable|uuid',
        ]);

        $user = $request->user();
        $tenantId = $user->tenant_id;

        // Station-ID: aus Device ableiten, oder erste Station des Tenants
        $stationId = null;
        if ($request->filled('device_token')) {
            $device = $this->findDevice($request->input('device_token'));
            if ($device && $device->station_id) {
                $stationId = $device->station_id;
            }
        }
        if (! $stationId) {
            $stationId = \App\Models\GasStation::where('tenant_id', $tenantId)->first()?->id;
        }

        Log::info('Gutschein-Generate: Station-Ermittlung', [
            'tenant_id' => $tenantId,
            'station_id' => $stationId,
        ]);

        // Konflikte pruefen (station-scoped)
        $conflicts = Voucher::checkGroupConflict($data['voucher_group'], $data['quantity'], $tenantId, $stationId);
        if ($conflicts) {
            return $this->error(
                'Gutscheinnummern existieren bereits: ' . implode(', ', array_slice($conflicts, 0, 5)),
                409,
                ['conflicts' => $conflicts]
            );
        }

        try {
            $vouchers = DB::transaction(function () use ($data, $stationId, $tenantId, $user) {
                return Voucher::generateGroup(
                    group: $data['voucher_group'],
                    quantity: $data['quantity'],
                    amount: (float) $data['amount'],
                    stationId: $stationId,
                    tenantId: $tenantId,
                    employeeId: $user->id,
                    employeeName: $user->name,
                );
            });

            Log::info('Gutscheine generiert via API', [
                'group' => $data['voucher_group'],
                'quantity' => $data['quantity'],
                'amount' => $data['amount'],
                'user' => $user->name,
            ]);

            $first = $vouchers->first();
            $last = $vouchers->last();

            // Print-Job automatisch in die Queue legen (Agent druckt an der Station)
            $this->createPrintJob($vouchers, $stationId, $user->name, $data['target_agent_id'] ?? null, $user->id);

            return $this->success([
                'count' => $vouchers->count(),
                'voucher_group' => $data['voucher_group'],
                'first_number' => $first->voucher_number,
                'last_number' => $last->voucher_number,
                'amount' => $first->amount,
                'valid_until' => $first->valid_until->format('Y-m-d'),
                'vouchers' => $vouchers->map(fn ($v) => $this->formatVoucher($v))->all(),
            ], "{$vouchers->count()} Gutscheine generiert");

        } catch (\Throwable $e) {
            Log::error('Gutschein-Generierung via API fehlgeschlagen', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            return $this->error('Generierung fehlgeschlagen: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/vouchers/redeem
     * {
     *   "voucher_number": "4567.023",
     *   "amount": 30.00,
     *   "notes": "optional"
     * }
     *
     * Loest einen Gutschein ganz oder teilweise ein.
     */
    public function redeem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'voucher_number' => 'required|string|max:30',
            'amount' => 'required|numeric|min:0.01|max:9999.99',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $tenantId = $user->tenant_id;

        $voucher = Voucher::where('tenant_id', $tenantId)
            ->where('voucher_number', $data['voucher_number'])
            ->first();

        if (! $voucher) {
            return $this->error("Gutschein '{$data['voucher_number']}' nicht gefunden.", 404);
        }

        if (! $voucher->isRedeemable()) {
            return $this->error(
                "Gutschein nicht einloesbar (Status: {$voucher->status_label}, gueltig bis: {$voucher->valid_until->format('d.m.Y')})",
                422
            );
        }

        if ($data['amount'] > $voucher->remaining_amount) {
            return $this->error(
                "Betrag ({$data['amount']}) uebersteigt Restguthaben ({$voucher->remaining_amount})",
                422,
                [
                    'remaining_amount' => $voucher->remaining_amount,
                    'requested_amount' => $data['amount'],
                ]
            );
        }

        try {
            $redemption = DB::transaction(function () use ($voucher, $data, $user, $tenantId) {
                return $voucher->redeem(
                    redeemAmount: (float) $data['amount'],
                    stationId: $tenantId,
                    employeeId: $user->id,
                    employeeName: $user->name,
                    notes: $data['notes'] ?? null,
                );
            });

            // Frisch laden
            $voucher->refresh();

            Log::info('Gutschein eingeloest via API', [
                'voucher' => $voucher->voucher_number,
                'amount' => $data['amount'],
                'remaining' => $voucher->remaining_amount,
                'user' => $user->name,
            ]);

            return $this->success([
                'voucher' => $this->formatVoucher($voucher),
                'redemption' => [
                    'id' => $redemption->id,
                    'amount' => (float) $redemption->amount,
                    'remaining_after' => (float) $redemption->remaining_after,
                    'redeemed_at' => $redemption->redeemed_at->toIso8601String(),
                    'employee_name' => $redemption->employee_name,
                ],
            ], "Gutschein eingeloest: {$redemption->formatted_amount}");

        } catch (\Throwable $e) {
            Log::error('Gutschein-Einloesung via API fehlgeschlagen', [
                'error' => $e->getMessage(),
                'voucher' => $data['voucher_number'],
            ]);
            return $this->error('Einloesung fehlgeschlagen: ' . $e->getMessage(), 500);
        }
    }

    // ── Private Helpers ─────────────────────────────────

    /**
     * Gutschein als API-Response formatieren.
     */
    private function formatVoucher(Voucher $v): array
    {
        return [
            'id' => $v->id,
            'voucher_number' => $v->voucher_number,
            'voucher_group' => $v->voucher_group,
            'amount' => (float) $v->amount,
            'remaining_amount' => (float) $v->remaining_amount,
            'formatted_amount' => $v->formatted_amount,
            'formatted_remaining' => $v->formatted_remaining,
            'amount_words' => Voucher::amountToWords($v->amount),
            'status' => $v->status,
            'status_label' => $v->status_label,
            'is_redeemable' => $v->isRedeemable(),
            'issued_at' => $v->issued_at->format('Y-m-d'),
            'issued_at_formatted' => $v->issued_at->format('d.m.Y'),
            'valid_until' => $v->valid_until->format('Y-m-d'),
            'valid_until_formatted' => $v->valid_until->format('d.m.Y'),
            'employee_name' => $v->employee_name,
            'redemptions_count' => $v->redemptions_count ?? $v->redemptions()->count(),
            'redemptions' => $v->relationLoaded('redemptions')
                ? $v->redemptions->map(fn ($r) => [
                    'id' => $r->id,
                    'amount' => (float) $r->amount,
                    'remaining_after' => (float) $r->remaining_after,
                    'redeemed_at' => $r->redeemed_at->toIso8601String(),
                    'employee_name' => $r->employee_name,
                    'notes' => $r->notes,
                ])->all()
                : null,
        ];
    }

    /**
     * Tenant-ID aus device_token ableiten.
     */
    private function resolveTenant(Request $request): ?string
    {
        $device = $this->findDevice(
            $request->query('device_token', $request->input('device_token', ''))
        );
        return $device?->tenant_id;
    }

    private function findDevice(string $plainToken): ?Device
    {
        return Device::findByPlainToken($plainToken);
    }

    /**
     * Gutschein-Etiketten in die Druck-Queue legen.
     * Rendert die Label-XMLs aus der 'gutschein'-Vorlage und uebergibt sie an
     * den PrintQueueService (setzt station_id/printer/TTL) -> der Stations-Agent
     * holt und druckt sie.
     */
    private function createPrintJob($vouchers, ?string $stationId, string $createdBy, ?string $targetAgentId = null, ?string $userId = null): void
    {
        try {
            if (! $stationId) {
                Log::warning('Print-Job: Keine Station fuer Gutschein-Druck ermittelbar');
                return;
            }

            $station = \App\Models\GasStation::find($stationId);
            if (! $station) {
                return;
            }

            $template = \App\Models\LabelTemplate::findForTenant('gutschein', $station->tenant_id);
            if (! $template) {
                Log::warning('Print-Job: Keine Gutschein-Druckvorlage', ['tenant_id' => $station->tenant_id]);
                return;
            }

            $labels = [];
            foreach ($vouchers as $voucher) {
                $labelData = [
                    'betrag' => number_format($voucher->amount, 2, ',', '.') . ' €',
                    'betrag_worte' => Voucher::amountToWords($voucher->amount),
                    'datum' => $voucher->issued_at->format('d.m.Y'),
                    'gueltig_bis' => $voucher->valid_until->format('d.m.Y'),
                    'nummer' => $voucher->voucher_number,
                    'barcode' => 'www.aral-welle.de',
                ];
                $labels[] = [
                    'number' => $voucher->voucher_number,
                    'xml' => $template->render($labelData),
                ];
            }

            if (empty($labels)) {
                return;
            }

            app(\App\Services\PrintQueueService::class)->enqueue(
                $station,
                'voucher_labels',
                $labels,
                [
                    'reference' => $vouchers->first()->voucher_group,
                    'reference_type' => 'voucher',
                    'created_by' => $createdBy,
                    'target_agent_id' => $targetAgentId,
                    'user_id' => $userId,
                ],
            );

            Log::info('Gutschein-Druckjob in Queue', [
                'group' => $vouchers->first()->voucher_group,
                'labels' => count($labels),
                'station_id' => $stationId,
            ]);
        } catch (\Throwable $e) {
            // Print-Job Fehler soll Generate nicht blockieren
            Log::error('Print-Job Erstellung fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }
}
