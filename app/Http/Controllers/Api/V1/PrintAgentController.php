<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AppVersion;
use App\Models\GasStation;
use App\Models\PrintAgent;
use App\Models\PrintJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Schnittstelle fuer den "ROSI Print"-Agent (Tray-App am Stations-PC).
 * Auth per Agent-Token (sha256-Hash in print_agents). Der Agent:
 *  - meldet sich + seine lokalen Drucker (heartbeat),
 *  - holt offene Druckjobs seiner Station (claim -> printing),
 *  - quittiert Erfolg/Fehler (ack).
 */
class PrintAgentController extends ApiController
{
    private const CLAIM_LIMIT = 20;

    /**
     * POST /api/v1/print/agent/enroll
     * Stations-Installer: bindet den Agent ueber den Enrollment-Token der
     * Station automatisch ein und gibt ein Agent-Token zurueck.
     * Body: enroll_token, install_id, hostname?, printers[]?
     */
    public function enroll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enroll_token' => 'required|string',
            'install_id' => 'required|string|max:191',
            'hostname' => 'nullable|string|max:191',
            'printers' => 'nullable|array',
            'printers.*' => 'string',
        ]);

        $station = GasStation::where('enrollment_token', $validated['enroll_token'])->first();
        if (! $station) {
            return $this->error('Ungueltiger Enrollment-Token.', 401);
        }

        // Ein PC = ein Agent (per install_id). Vorhandenen wiederverwenden.
        $agent = PrintAgent::findByInstallId($validated['install_id'])
            ?? new PrintAgent(['install_id' => $validated['install_id']]);

        $agent->tenant_id = $station->tenant_id;
        $agent->station_id = $station->id;
        $agent->hostname = $validated['hostname'] ?? $agent->hostname;
        $agent->name = $agent->name ?: ($validated['hostname'] ?? 'Stations-PC');
        $agent->printers = $validated['printers'] ?? $agent->printers;
        $agent->status = PrintAgent::STATUS_ACTIVE;
        $agent->is_active = true;
        $agent->claim_secret = null;
        $token = $agent->generateToken();
        $agent->last_seen_at = now();
        $agent->save();

        return $this->success([
            'token' => $token,
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
            'station' => $station->name,
        ], 'Agent verbunden.');
    }

    /**
     * POST /api/v1/print/agent/register
     * Self-Register (generische EXE ohne Enrollment-Token): legt den Agent als
     * "pending" an. Freigabe + Stationszuweisung erfolgt im Dashboard.
     * Body: install_id, hostname?, printers[]?
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'install_id' => 'required|string|max:191',
            'hostname' => 'nullable|string|max:191',
            'printers' => 'nullable|array',
            'printers.*' => 'string',
        ]);

        $agent = PrintAgent::findByInstallId($validated['install_id']);

        // Bereits freigegeben? -> Agent soll Token abholen.
        if ($agent && $agent->status === PrintAgent::STATUS_ACTIVE && $agent->station_id) {
            return $this->success(['status' => PrintAgent::STATUS_ACTIVE], 'Bereits freigegeben.');
        }

        if (! $agent) {
            $agent = new PrintAgent(['install_id' => $validated['install_id']]);
            $agent->claim_secret = Str::random(48);
            $agent->name = $validated['hostname'] ?? 'Neuer PC';
        }

        $agent->hostname = $validated['hostname'] ?? $agent->hostname;
        $agent->printers = $validated['printers'] ?? $agent->printers;
        $agent->status = PrintAgent::STATUS_PENDING;
        $agent->is_active = false;
        $agent->last_seen_at = now();
        $agent->save();

        return $this->success([
            'status' => PrintAgent::STATUS_PENDING,
            'claim_secret' => $agent->claim_secret,
        ], 'Wartet auf Freigabe.');
    }

    /**
     * POST /api/v1/print/agent/claim-token
     * Self-Register: holt nach der Dashboard-Freigabe das Agent-Token ab.
     * Body: install_id, claim_secret
     */
    public function claimToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'install_id' => 'required|string|max:191',
            'claim_secret' => 'required|string',
        ]);

        $agent = PrintAgent::where('install_id', $validated['install_id'])
            ->where('claim_secret', $validated['claim_secret'])
            ->first();

        if (! $agent) {
            return $this->error('Unbekannter Agent.', 404);
        }

        if ($agent->status !== PrintAgent::STATUS_ACTIVE || ! $agent->station_id) {
            return $this->success(['status' => PrintAgent::STATUS_PENDING], 'Noch nicht freigegeben.');
        }

        $token = $agent->generateToken();
        $agent->claim_secret = null;
        $agent->is_active = true;
        $agent->save();

        return $this->success([
            'status' => PrintAgent::STATUS_ACTIVE,
            'token' => $token,
            'agent_id' => $agent->id,
            'station' => $agent->station?->name,
        ], 'Freigegeben.');
    }

    /**
     * POST /api/v1/print/agent/heartbeat
     * Body: token, printers[]?, app_version?
     * Aktualisiert Heartbeat + gemeldete Drucker.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $agent = $this->agent($request);
        if (! $agent) {
            return $this->error('Agent nicht erkannt.', 401);
        }

        $request->validate([
            'printers' => 'nullable|array',
            'printers.*' => 'string',
            'app_version' => 'nullable|string|max:50',
        ]);

        $agent->update([
            'printers' => $request->input('printers', $agent->printers),
            'app_version' => $request->input('app_version', $agent->app_version),
            'last_seen_at' => now(),
        ]);

        return $this->success([
            'agent' => $agent->name,
            'station' => $agent->station?->name,
            'pending' => PrintJob::queuedForAgent($agent)->count(),
            'update' => $this->agentUpdatePayload(),
        ], 'OK');
    }

    /**
     * Neueste installierbare Agent-Version (Plattform print-agent) als
     * Update-Info fuer das stille Auto-Update. null = kein Update verfuegbar.
     */
    private function agentUpdatePayload(): ?array
    {
        $latest = AppVersion::published()
            ->where('platform', 'print-agent')
            ->whereNotNull('version_code')
            ->whereNotNull('apk_path')
            ->orderByDesc('version_code')
            ->get()
            ->first(fn (AppVersion $v) => $v->hasApk());

        if (! $latest) {
            return null;
        }

        return [
            'version' => $latest->version,
            'version_code' => $latest->version_code,
            'mandatory' => $latest->is_mandatory,
            'changes' => $latest->changes ?? [],
            'download_url' => route('api.v1.print.agent.version.download', ['version' => $latest->version]),
        ];
    }

    /**
     * POST /api/v1/print/agent/jobs/claim
     * Holt offene Jobs der Station und markiert sie als 'printing'
     * (atomar, damit kein Job doppelt gedruckt wird).
     */
    public function claim(Request $request): JsonResponse
    {
        $agent = $this->agent($request);
        if (! $agent) {
            return $this->error('Agent nicht erkannt.', 401);
        }

        $agent->update(['last_seen_at' => now()]);

        $jobs = DB::transaction(function () use ($agent) {
            $pending = PrintJob::queuedForAgent($agent)
                ->lockForUpdate()
                ->limit(self::CLAIM_LIMIT)
                ->get();

            foreach ($pending as $job) {
                $job->update([
                    'status' => PrintJob::STATUS_PRINTING,
                    'agent_id' => $agent->id,
                    'attempts' => $job->attempts + 1,
                ]);
            }

            return $pending;
        });

        $data = $jobs->map(fn (PrintJob $j) => [
            'id' => $j->id,
            'job_type' => $j->job_type,
            'reference' => $j->reference,
            'printer_name' => $j->printer_name,
            'labels' => $j->payload,
            'created_by' => $j->created_by,
            // Bei Nachdrucken ODER vielen Etiketten langsamer drucken
            // (verhindert leere/verschluckte Labels durch Spooler-Ueberlauf).
            'pace' => $j->reference_type === 'voucher_reprint' || count((array) $j->payload) > 10,
        ])->values();

        return $this->success(['jobs' => $data], $jobs->count() . ' Job(s)');
    }

    /**
     * POST /api/v1/print/agent/jobs/{id}/ack
     * Body: token, success(bool), error_message?
     */
    public function ack(Request $request, string $id): JsonResponse
    {
        $agent = $this->agent($request);
        if (! $agent) {
            return $this->error('Agent nicht erkannt.', 401);
        }

        $request->validate([
            'success' => 'required|boolean',
            'error_message' => 'nullable|string|max:500',
        ]);

        $job = PrintJob::where('id', $id)
            ->where('station_id', $agent->station_id)
            ->first();

        if (! $job) {
            return $this->error('Job nicht gefunden.', 404);
        }

        if ($request->boolean('success')) {
            $job->update([
                'status' => PrintJob::STATUS_DONE,
                'printed_at' => now(),
                'agent_id' => $agent->id,
                'error_message' => null,
            ]);

            return $this->success(null, 'Gedruckt.');
        }

        $job->update([
            'status' => PrintJob::STATUS_FAILED,
            'agent_id' => $agent->id,
            'error_message' => $request->input('error_message'),
        ]);

        return $this->success(null, 'Fehler vermerkt.');
    }

    private function agent(Request $request): ?PrintAgent
    {
        $token = $request->input('token') ?: $request->bearerToken();

        return PrintAgent::findByToken($token);
    }
}
