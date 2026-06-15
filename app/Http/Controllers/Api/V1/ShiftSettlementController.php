<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Device;
use App\Models\GasStation;
use App\Models\ShiftReturnReason;
use App\Models\ShiftSettlement;
use App\Models\ShiftSettlementCheckQuestion;
use App\Models\ShiftSettlementCoinRoll;
use App\Models\TenantSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Schichtabrechnung-API fuer POS-App.
 *
 * Ablauf:
 * 1. Prueffragen abrufen → GET /shift-settlements/check-questions
 * 2. Schicht starten → POST /shift-settlements/start
 * 3. Waehrend der Schicht:
 *    - Tresor-Einlage → POST /shift-settlements/{id}/safe-deposits
 *    - Warenruecknahme → POST /shift-settlements/{id}/returns
 * 4. Schicht beenden → POST /shift-settlements/{id}/complete
 * 5. Aktive Schicht pruefen → GET /shift-settlements/active
 */
class ShiftSettlementController extends ApiController
{
    // ── Prueffragen ──────────────────────────────────────

    /**
     * GET /api/v1/shift-settlements/check-questions?device_token=xxx
     *
     * Prueffragen fuer die Station des Geraets abrufen.
     */
    public function checkQuestions(Request $request): JsonResponse
    {
        $device = $this->findDevice($request->query('device_token', ''));
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $questions = ShiftSettlementCheckQuestion::where('tenant_id', $device->tenant_id)
            ->active()
            ->forStation($device->station_id)
            ->get()
            ->map(fn ($q) => [
                'id' => $q->id,
                'question_text' => $q->question_text,
                'question_type' => $q->question_type,
                'sort_order' => $q->sort_order,
            ]);

        return $this->success([
            'questions' => $questions,
        ]);
    }

    // ── Ruecknahme-Gruende ─────────────────────────────────

    /**
     * GET /api/v1/shift-settlements/return-reasons?device_token=xxx
     *
     * Aktive Warenruecknahme-Gruende fuer den Tenant des Geraets abrufen.
     * Global (tenant_id=null) + tenant-spezifische Gruende.
     * "Sonstiges" wird immer als letzter Eintrag angehaengt (hardcoded).
     */
    public function returnReasons(Request $request): JsonResponse
    {
        $device = $this->findDevice($request->query('device_token', ''));
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $reasons = ShiftReturnReason::active()
            ->forTenant($device->tenant_id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'label' => $r->label,
            ])
            ->values()
            ->toArray();

        // "Sonstiges" immer als letzten Eintrag anhaengen
        $reasons[] = [
            'id' => 'sonstiges',
            'label' => 'Sonstiges',
        ];

        return $this->success([
            'reasons' => $reasons,
        ]);
    }

    // ── Schicht starten ──────────────────────────────────

    /**
     * POST /api/v1/shift-settlements/start
     *
     * Neue Schichtabrechnung starten.
     * Erwartet: device_token, check_answers[], coin_rolls[],
     *           counters[] (Anfangsbestaende).
     */
    public function start(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Nicht angemeldet.', 401);
        }

        $device = $this->findDevice($request->input('device_token', ''));
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $station = GasStation::find($device->station_id);
        if (! $station) {
            return $this->error('Tankstelle nicht gefunden.', 404);
        }

        // Pruefen ob schon eine aktive Schicht laeuft
        $activeShift = ShiftSettlement::where('user_id', $user->id)
            ->where('gas_station_id', $station->id)
            ->where('status', 'active')
            ->first();

        if ($activeShift) {
            return $this->error('Es laeuft bereits eine aktive Schicht.', 409);
        }

        $validated = $request->validate([
            // Prueffragen
            'check_answers' => 'nullable|array',
            'check_answers.*.question_id' => 'required|uuid',
            'check_answers.*.answer_bool' => 'nullable|boolean',
            'check_answers.*.answer_text' => 'nullable|string|max:1000',
            'check_answers.*.answer_number' => 'nullable|numeric',
            // Muenzrollen Anfang
            'coin_rolls' => 'nullable|array',
            'coin_rolls.*.denomination' => 'required|integer|in:200,100,50,20,10,5,2,1',
            'coin_rolls.*.count_start' => 'required|integer|min:0',
            // Zaehlerstaende Anfang
            'counters' => 'nullable|array',
            'counters.*.counter_type' => 'required|string|max:30',
            'counters.*.counter_label' => 'nullable|string|max:255',
            'counters.*.value_start' => 'required|numeric|min:0',
        ]);

        // Schicht anlegen
        $settlement = ShiftSettlement::create([
            'tenant_id' => $station->tenant_id,
            'gas_station_id' => $station->id,
            'user_id' => $user->id,
            'status' => 'active',
            'started_at' => now(),
        ]);

        // Prueffragen-Antworten speichern
        if (! empty($validated['check_answers'])) {
            foreach ($validated['check_answers'] as $answer) {
                $settlement->checkAnswers()->create([
                    'question_id' => $answer['question_id'],
                    'answer_bool' => $answer['answer_bool'] ?? null,
                    'answer_text' => $answer['answer_text'] ?? null,
                    'answer_number' => $answer['answer_number'] ?? null,
                ]);
            }
        }

        // Muenzrollen Anfangsbestand
        $rollValues = [
            200 => 50.00, 100 => 25.00, 50 => 20.00, 20 => 8.00,
            10 => 4.00, 5 => 2.50, 2 => 1.00, 1 => 0.50,
        ];

        if (! empty($validated['coin_rolls'])) {
            foreach ($validated['coin_rolls'] as $roll) {
                $denomination = $roll['denomination'];
                $settlement->coinRolls()->create([
                    'denomination' => $denomination,
                    'roll_value' => $rollValues[$denomination] ?? 0,
                    'count_start' => $roll['count_start'],
                    'count_end' => 0,
                ]);
            }
        }

        // Zaehlerstaende Anfang
        if (! empty($validated['counters'])) {
            foreach ($validated['counters'] as $counter) {
                $settlement->counters()->create([
                    'counter_type' => $counter['counter_type'],
                    'counter_label' => $counter['counter_label'] ?? null,
                    'value_start' => $counter['value_start'],
                    'value_end' => 0,
                ]);
            }
        }

        return $this->success([
            'id' => $settlement->id,
            'status' => $settlement->status,
            'started_at' => $settlement->started_at->toIso8601String(),
        ], 'Schicht gestartet.', 201);
    }

    // ── Aktive Schicht abrufen ───────────────────────────

    /**
     * GET /api/v1/shift-settlements/active?device_token=xxx
     *
     * Aktive Schicht des angemeldeten Mitarbeiters abrufen.
     */
    public function active(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Nicht angemeldet.', 401);
        }

        $device = $this->findDevice($request->query('device_token', ''));
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $settlement = ShiftSettlement::where('user_id', $user->id)
            ->where('gas_station_id', $device->station_id)
            ->where('status', 'active')
            ->with(['coinRolls', 'counters', 'safeDeposits', 'returns'])
            ->first();

        if (! $settlement) {
            return $this->success(['settlement' => null], 'Keine aktive Schicht.');
        }

        return $this->success([
            'settlement' => $this->formatSettlement($settlement),
        ]);
    }

    // ── Letzte Werte der Station ─────────────────────────

    /**
     * GET /api/v1/shift-settlements/last-values?device_token=xxx
     *
     * Liefert die Endwerte der letzten abgeschlossenen Schicht
     * der gleichen Tankstelle (egal welcher Mitarbeiter).
     * Wird in der App genutzt um Anfangsbestaende vorzuschlagen.
     */
    public function lastValues(Request $request): JsonResponse
    {
        $device = $this->findDevice($request->query('device_token', ''));
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $last = ShiftSettlement::where('gas_station_id', $device->station_id)
            ->where('status', 'completed')
            ->with(['coinRolls', 'counters'])
            ->orderByDesc('ended_at')
            ->first();

        if (! $last) {
            return $this->success([
                'coin_rolls' => [],
                'counters' => [],
            ], 'Keine vorherige Schicht gefunden.');
        }

        return $this->success([
            'coin_rolls' => $last->coinRolls->map(fn ($r) => [
                'denomination' => $r->denomination,
                'roll_value' => (float) $r->roll_value,
                'count_end' => $r->count_end,
                'value_end' => (float) $r->value_end,
            ]),
            'counters' => $last->counters->map(fn ($c) => [
                'counter_type' => $c->counter_type,
                'counter_label' => $c->counter_label,
                'value_end' => (float) $c->value_end,
            ]),
        ]);
    }

    // ── Eigene Schichten (App-Liste) ─────────────────────

    /**
     * GET /api/v1/shift-settlements/mine?device_token=xxx
     *
     * Liefert alle Schichten des angemeldeten Mitarbeiters
     * (sortiert nach Beginn absteigend, max. 50).
     */
    public function mine(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Nicht angemeldet.', 401);
        }

        $device = $this->findDevice($request->query('device_token', ''));
        if (! $device) {
            return $this->error('Geraet nicht erkannt.', 401);
        }

        $settlements = ShiftSettlement::where('user_id', $user->id)
            ->where('gas_station_id', $device->station_id)
            ->orderByDesc('started_at')
            ->limit(50)
            ->get();

        return $this->success([
            'settlements' => $settlements->map(fn ($s) => [
                'id' => $s->id,
                'status' => $s->status,
                'started_at' => $s->started_at->toIso8601String(),
                'ended_at' => $s->ended_at?->toIso8601String(),
                'cash_remaining' => (float) $s->cash_remaining,
                'cash_report_soll' => (float) $s->cash_report_soll,
                'cash_difference' => (float) $s->cash_difference,
                'safe_total' => (float) $s->safe_total,
            ]),
        ]);
    }

    /**
     * GET /api/v1/shift-settlements/{id}/details?device_token=xxx
     *
     * Vollstaendige Details einer eigenen Schicht (read-only).
     */
    public function details(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Nicht angemeldet.', 401);
        }

        $settlement = ShiftSettlement::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['coinRolls', 'counters', 'safeDeposits', 'returns', 'comments.user'])
            ->first();

        if (! $settlement) {
            return $this->error('Schicht nicht gefunden.', 404);
        }

        return $this->success([
            'settlement' => $this->formatSettlement($settlement),
            'comments' => $settlement->comments->map(fn ($c) => [
                'id' => $c->id,
                'body' => $c->body,
                'user_name' => $c->user?->name ?? '—',
                'created_at' => $c->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * POST /api/v1/shift-settlements/{id}/comments
     *
     * Nachtraeglicher Kommentar zu einer eigenen Schicht.
     * Funktioniert auch nach Abschluss.
     */
    public function addComment(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->error('Nicht angemeldet.', 401);
        }

        $settlement = ShiftSettlement::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $settlement) {
            return $this->error('Schicht nicht gefunden.', 404);
        }

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $comment = $settlement->comments()->create([
            'user_id' => $user->id,
            'body' => $validated['body'],
        ]);

        return $this->success([
            'id' => $comment->id,
            'body' => $comment->body,
            'user_name' => $user->name,
            'created_at' => $comment->created_at->toIso8601String(),
        ], 'Kommentar gespeichert.', 201);
    }

    // ── Tresor-Einlage ───────────────────────────────────

    /**
     * POST /api/v1/shift-settlements/{id}/safe-deposits
     *
     * Tresor-Einlage / Safebag hinzufuegen.
     * Gibt Barcode zurueck fuer DYMO-Etikett.
     */
    public function addSafeDeposit(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $settlement = $this->findActiveSettlement($id, $user);
        if (! $settlement) {
            return $this->error('Schicht nicht gefunden oder nicht aktiv.', 404);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'has_coins' => 'nullable|boolean',
        ]);

        // Barcode generieren (URL zum Schicht-Datensatz)
        $barcode = 'SAFE-' . strtoupper(Str::random(8));

        $deposit = $settlement->safeDeposits()->create([
            'amount' => $validated['amount'],
            'has_coins' => $validated['has_coins'] ?? false,
            'deposited_at' => now(),
            'barcode' => $barcode,
        ]);

        // Daten fuer Etikett
        $station = $settlement->gasStation;

        // Tenant-Einstellung: DYMO-Etikett automatisch drucken? (Default: ja)
        $autoPrint = filter_var(
            TenantSetting::get('auto_print_safe_label', '1', $settlement->tenant_id, 'shift'),
            FILTER_VALIDATE_BOOLEAN,
        );

        // Tresor-Etikett in die Druck-Queue legen (nur wenn Vorlage vorhanden) ->
        // der Stations-Agent druckt es lokal.
        if ($autoPrint && \App\Models\LabelTemplate::findForTenant('tresor', $settlement->tenant_id)) {
            try {
                app(\App\Services\PrintQueueService::class)->enqueueFromTemplate(
                    $station,
                    'tresor',
                    [[
                        'station' => $station->name ?? 'Station',
                        'mitarbeiter' => $user->name,
                        'datum' => now()->format('d.m.Y'),
                        'zeit' => now()->format('H:i'),
                        'betrag' => number_format((float) $validated['amount'], 2, ',', '.') . ' EUR',
                        'barcode' => $barcode,
                        '_number' => 1,
                    ]],
                    [
                        'job_type' => 'safe_deposit',
                        'reference' => $barcode,
                        'reference_type' => 'safe_deposit',
                        'created_by' => $user->name,
                    ],
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Tresor-Etikett nicht in Queue: ' . $e->getMessage());
            }
        }

        return $this->success([
            'id' => $deposit->id,
            'barcode' => $barcode,
            'auto_print_label' => $autoPrint,
            'label_data' => [
                'station_name' => $station->name ?? 'Station',
                'employee_name' => $user->name,
                'date' => now()->format('d.m.Y'),
                'time' => now()->format('H:i'),
                'amount' => number_format((float) $validated['amount'], 2, ',', '.') . ' EUR',
                'has_coins' => $validated['has_coins'] ?? false,
                'barcode' => $barcode,
            ],
        ], 'Tresor-Einlage erfasst.', 201);
    }

    // ── Warenruecknahme ──────────────────────────────────

    /**
     * POST /api/v1/shift-settlements/{id}/returns
     *
     * Warenruecknahme / Storno hinzufuegen (mit Bon-Foto).
     */
    public function addReturn(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $settlement = $this->findActiveSettlement($id, $user);
        if (! $settlement) {
            return $this->error('Schicht nicht gefunden oder nicht aktiv.', 404);
        }

        $validated = $request->validate([
            'receipt_number' => 'nullable|string|max:50',
            'time' => 'required|date_format:H:i',
            'product' => 'required|string|max:255',
            'reason' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        // Foto speichern
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')
                ->store('shift-returns/' . $settlement->id, 'public');
        }

        $return = $settlement->returns()->create([
            'receipt_number' => $validated['receipt_number'],
            'time' => $validated['time'],
            'product' => $validated['product'],
            'reason' => $validated['reason'],
            'amount' => $validated['amount'],
            'photo' => $photoPath,
        ]);

        return $this->success([
            'id' => $return->id,
        ], 'Warenruecknahme erfasst.', 201);
    }

    // ── Schicht beenden ──────────────────────────────────

    /**
     * POST /api/v1/shift-settlements/{id}/complete
     *
     * Schicht abschliessen mit Endbestaenden.
     * Erwartet: coin_rolls[] (Endbestaende), counters[] (Endbestaende),
     *           cash_remaining, cash_report_soll, signature,
     *           cash_report_photo (Foto).
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $settlement = $this->findActiveSettlement($id, $user);
        if (! $settlement) {
            return $this->error('Schicht nicht gefunden oder nicht aktiv.', 404);
        }

        // Multipart: coin_rolls/counters kommen als JSON-String von der App
        if (is_string($request->input('coin_rolls'))) {
            $request->merge(['coin_rolls' => json_decode($request->input('coin_rolls'), true) ?? []]);
        }
        if (is_string($request->input('counters'))) {
            $request->merge(['counters' => json_decode($request->input('counters'), true) ?? []]);
        }

        $validated = $request->validate([
            // Endbestaende Muenzrollen
            'coin_rolls' => 'nullable|array',
            'coin_rolls.*.denomination' => 'required|integer|in:200,100,50,20,10,5,2,1',
            'coin_rolls.*.count_end' => 'required|integer|min:0',
            // Endbestaende Zaehler
            'counters' => 'nullable|array',
            'counters.*.counter_type' => 'required|string|max:30',
            'counters.*.value_end' => 'required|numeric|min:0',
            // Kasse
            'cash_remaining' => 'required|numeric|min:0',
            'cash_report_soll' => 'nullable|numeric|min:0',
            // Abschluss
            'signature' => 'required|string',
            'notes' => 'nullable|string|max:2000',
            'cash_report_photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        // Muenzrollen Endbestand aktualisieren
        if (! empty($validated['coin_rolls'])) {
            foreach ($validated['coin_rolls'] as $roll) {
                $existing = $settlement->coinRolls()
                    ->where('denomination', $roll['denomination'])
                    ->first();

                if ($existing) {
                    $existing->update(['count_end' => $roll['count_end']]);
                }
            }
        }

        // Zaehlerstaende Ende aktualisieren
        if (! empty($validated['counters'])) {
            foreach ($validated['counters'] as $counter) {
                $existing = $settlement->counters()
                    ->where('counter_type', $counter['counter_type'])
                    ->first();

                if ($existing) {
                    $existing->update(['value_end' => $counter['value_end']]);
                }
            }
        }

        // Kassenbericht-Foto speichern
        $photoPath = null;
        if ($request->hasFile('cash_report_photo')) {
            $photoPath = $request->file('cash_report_photo')
                ->store('shift-settlements/' . $settlement->id, 'public');
        }

        // Schicht-Daten aktualisieren
        $settlement->update([
            'cash_remaining' => $validated['cash_remaining'],
            'cash_report_soll' => $validated['cash_report_soll'] ?? 0,
            'signature' => $validated['signature'],
            'notes' => $validated['notes'] ?? null,
            'cash_report_photo' => $photoPath,
        ]);

        // Schicht abschliessen (berechnet safe_total + cash_difference)
        $settlement->complete();

        // Frisch laden mit allen Beziehungen
        $settlement->refresh();
        $settlement->load(['coinRolls', 'counters', 'safeDeposits', 'returns']);

        return $this->success([
            'settlement' => $this->formatSettlement($settlement),
        ], 'Schicht abgeschlossen.');
    }

    // ── Hilfsmethoden ────────────────────────────────────

    /**
     * Aktive Schichtabrechnung des Benutzers finden.
     */
    private function findActiveSettlement(string $id, $user): ?ShiftSettlement
    {
        if (! $user) {
            return null;
        }

        return ShiftSettlement::where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Schichtabrechnung als Array formatieren.
     */
    private function formatSettlement(ShiftSettlement $s): array
    {
        return [
            'id' => $s->id,
            'status' => $s->status,
            'started_at' => $s->started_at->toIso8601String(),
            'ended_at' => $s->ended_at?->toIso8601String(),
            'cash_remaining' => (float) $s->cash_remaining,
            'safe_total' => (float) $s->safe_total,
            'cash_report_soll' => (float) $s->cash_report_soll,
            'cash_difference' => (float) $s->cash_difference,
            'coin_rolls' => $s->coinRolls->map(fn ($r) => [
                'denomination' => $r->denomination,
                'roll_value' => (float) $r->roll_value,
                'count_start' => $r->count_start,
                'count_end' => $r->count_end,
                'count_used' => $r->count_used,
                'value_start' => (float) $r->value_start,
                'value_end' => (float) $r->value_end,
                'value_used' => (float) $r->value_used,
            ]),
            'counters' => $s->counters->map(fn ($c) => [
                'counter_type' => $c->counter_type,
                'counter_label' => $c->counter_label,
                'display_name' => $c->display_name,
                'value_start' => (float) $c->value_start,
                'value_end' => (float) $c->value_end,
                'value_used' => (float) $c->value_used,
            ]),
            'safe_deposits' => $s->safeDeposits->map(fn ($d) => [
                'id' => $d->id,
                'amount' => (float) $d->amount,
                'has_coins' => $d->has_coins,
                'deposited_at' => $d->deposited_at->toIso8601String(),
                'barcode' => $d->barcode,
            ]),
            'returns' => $s->returns->map(fn ($r) => [
                'id' => $r->id,
                'receipt_number' => $r->receipt_number,
                'time' => $r->time,
                'product' => $r->product,
                'reason' => $r->reason,
                'amount' => (float) $r->amount,
            ]),
        ];
    }

    /**
     * Geraet anhand des Klartext-Tokens finden.
     */
    private function findDevice(string $plainToken): ?Device
    {
        return Device::findByPlainToken($plainToken);
    }
}
