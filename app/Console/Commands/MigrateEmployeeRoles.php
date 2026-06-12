<?php

namespace App\Console\Commands;

use App\Models\EmployeeStationRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Einmalige Bestandsdaten-Migration ins neue Rollensystem.
 *
 * 1. gas_station_user.station_role → employee_station_roles:
 *    station_manager → stationsleiter, shift_leader → schichtleiter,
 *    alles andere (cashier, attendant, ...) → kassierer (je an der Station)
 * 2. Aktive Mitarbeiter ohne jede Zuweisung → kassierer (ganzer Betrieb)
 *
 * Idempotent: bestehende Zuweisungen werden nie doppelt angelegt.
 */
class MigrateEmployeeRoles extends Command
{
    protected $signature = 'rosi:roles-migrate {--dry-run : Nur anzeigen, nichts schreiben}';

    protected $description = 'Bestehende Mitarbeiter ins neue Rollensystem uebernehmen (station_role-Pivot + Default kassierer)';

    private const ROLLEN_MAPPING = [
        'station_manager' => 'stationsleiter',
        'shift_leader' => 'schichtleiter',
        // alle uebrigen Stations-Rollen → kassierer
    ];

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $angelegt = 0;

        foreach (Tenant::all() as $tenant) {
            $rollen = Role::where('tenant_id', $tenant->id)->pluck('id', 'name');

            if (! isset($rollen['kassierer'])) {
                $this->warn("Tenant {$tenant->name}: System-Rollen fehlen — erst rosi:permissions-sync ausfuehren.");
                continue;
            }

            // 1. Stations-Rollen aus dem Pivot uebernehmen
            $pivots = DB::table('gas_station_user')
                ->join('users', 'users.id', '=', 'gas_station_user.user_id')
                ->where('users.tenant_id', $tenant->id)
                ->select('gas_station_user.user_id', 'gas_station_user.gas_station_id', 'gas_station_user.station_role')
                ->get();

            foreach ($pivots as $pivot) {
                $zielRolle = self::ROLLEN_MAPPING[$pivot->station_role] ?? 'kassierer';
                $rolleId = $rollen[$zielRolle] ?? null;
                if (! $rolleId) {
                    continue;
                }

                $existiert = EmployeeStationRole::withoutTenantScope()
                    ->where('user_id', $pivot->user_id)
                    ->where('role_id', $rolleId)
                    ->where('gas_station_id', $pivot->gas_station_id)
                    ->exists();

                if (! $existiert) {
                    $this->line("  {$pivot->user_id} → {$zielRolle} @ Station {$pivot->gas_station_id}");
                    if (! $dry) {
                        EmployeeStationRole::create([
                            'tenant_id' => $tenant->id,
                            'user_id' => $pivot->user_id,
                            'role_id' => $rolleId,
                            'gas_station_id' => $pivot->gas_station_id,
                        ]);
                    }
                    $angelegt++;
                }
            }

            // 2. Aktive Mitarbeiter ganz ohne Zuweisung → kassierer betriebsweit
            $ohneRolle = User::where('tenant_id', $tenant->id)
                ->where('type', 'employee')
                ->where('is_active', true)
                ->whereDoesntHave('stationRoles')
                ->get();

            foreach ($ohneRolle as $user) {
                $this->line("  {$user->name} → kassierer (ganzer Betrieb)");
                if (! $dry) {
                    EmployeeStationRole::create([
                        'tenant_id' => $tenant->id,
                        'user_id' => $user->id,
                        'role_id' => $rollen['kassierer'],
                        'gas_station_id' => null,
                    ]);
                }
                $angelegt++;
            }
        }

        $this->info(($dry ? '[DRY-RUN] ' : '') . "{$angelegt} Zuweisungen " . ($dry ? 'wuerden angelegt.' : 'angelegt.'));

        return self::SUCCESS;
    }
}
