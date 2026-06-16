<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PrintAgent;
use App\Models\PrintJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        ], 'OK');
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
