<?php

namespace App\Services;

use App\Models\GasStation;
use App\Models\PrintAgent;

/**
 * Baut die Liste der Druckerziele (Agent + Drucker) einer Station.
 * Eine Quelle fuer die App (GET /print/destinations) UND das Partner-Dashboard
 * (Geraet -> Drucker festlegen), damit beide exakt dieselben Ziele anbieten.
 *
 * Schluessel-Format: "<agent_id>|<printer_name>".
 */
class PrintDestinationService
{
    /**
     * Alle Druckerziele der Station.
     *
     * @return array<int, array{agent_id:string, printer_name:string, key:string, label:string, agent_name:string, online:bool, is_default:bool}>
     */
    public function forStation(?GasStation $station): array
    {
        if (! $station) {
            return [];
        }

        $agents = PrintAgent::where('station_id', $station->id)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        $multiAgent = $agents->count() > 1;
        $defaultPrinter = $station->printer_map['*'] ?? null;

        $destinations = [];
        foreach ($agents as $agent) {
            foreach (($agent->printers ?? []) as $printer) {
                $destinations[] = [
                    'agent_id' => $agent->id,
                    'printer_name' => $printer,
                    'key' => $agent->id . '|' . $printer,
                    'agent_name' => $agent->name,
                    'label' => $multiAgent ? ($agent->name . ' · ' . $printer) : $printer,
                    'online' => $agent->online(),
                    'is_default' => ($defaultPrinter !== null && $printer === $defaultPrinter),
                ];
            }
        }

        return $destinations;
    }

    /**
     * Optionen fuer Filament-Selects: key => "Agent · Drucker".
     *
     * @return array<string, string>
     */
    public function optionsForStation(?GasStation $station): array
    {
        $options = [];
        foreach ($this->forStation($station) as $d) {
            $options[$d['key']] = $d['agent_name'] . ' · ' . $d['printer_name'];
        }

        return $options;
    }
}
