<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Synchronisiert den Permission-Katalog (config/permission-katalog.php) in die DB.
 *
 * Generisch — wird bei neuen Modulen NICHT angepasst. Ablauf:
 * 1. Neue Permissions aus dem Katalog anlegen (bestehende bleiben unberuehrt)
 * 2. Pro Mandant: System-Rollen sicherstellen (is_system, nicht loeschbar)
 * 3. Defaults verteilen — aber nur fuer NEU angelegte Permissions, damit
 *    Anpassungen der Partner an System-Rollen nie ueberschrieben werden.
 *    Die Rolle "partner" (Inhaber) bekommt immer ALLE Katalog-Permissions.
 * 4. Verwaiste Permissions (in DB, nicht mehr im Katalog) nur MELDEN —
 *    geloescht wird ausschliesslich mit --prune.
 *
 * Idempotent: Mehrfaches Ausfuehren aendert nichts.
 */
class SyncPermissionCatalog extends Command
{
    protected $signature = 'rosi:permissions-sync
        {--prune : Verwaiste Permissions loeschen}
        {--apply-defaults : Alle Katalog-Defaults erneut ADDITIV auf System-Rollen anwenden (Erst-Rollout / Werkseinstellungen)}';

    protected $description = 'Permission-Katalog in die Datenbank synchronisieren (Rollen, Permissions, Defaults)';

    public function handle(): int
    {
        $katalog = config('permission-katalog.ressourcen', []);
        $systemRollen = config('permission-katalog.system_rollen', []);

        if (empty($katalog)) {
            $this->error('Katalog ist leer — config/permission-katalog.php pruefen.');
            return self::FAILURE;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ── 1. Permissions anlegen ────────────────────────────────────
        $katalogPermissions = [];   // alle Namen laut Katalog
        $neuePermissions = [];      // in diesem Lauf neu angelegt

        foreach ($katalog as $ressourceKey => $ressource) {
            foreach (array_keys($ressource['aktionen']) as $aktion) {
                $name = "{$ressourceKey}.{$aktion}";
                $katalogPermissions[] = $name;

                if (! Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
                    Permission::create(['name' => $name, 'guard_name' => 'web']);
                    $neuePermissions[] = $name;
                }
            }
        }

        $this->info(count($katalogPermissions) . ' Permissions im Katalog, ' . count($neuePermissions) . ' neu angelegt.');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // ── 2.+3. Pro Mandant: Rollen + Defaults ─────────────────────
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

            foreach ($systemRollen as $rolleName => $rolleLabel) {
                $rolle = Role::where('name', $rolleName)
                    ->where('tenant_id', $tenant->id)
                    ->first();

                $istNeu = $rolle === null;
                if ($istNeu) {
                    $rolle = Role::create(['name' => $rolleName, 'guard_name' => 'web']);
                }

                // Als System-Rolle markieren (nicht loeschbar in der Matrix)
                if (! $rolle->is_system) {
                    $rolle->forceFill(['is_system' => true])->save();
                }

                // Inhaber bekommt immer alle Katalog-Permissions (additiv)
                if ($rolleName === 'partner') {
                    $rolle->givePermissionTo($katalogPermissions);
                    continue;
                }

                // Andere System-Rollen: Defaults verteilen —
                // bei neuer Rolle alle Defaults, sonst nur fuer neue Permissions.
                // --apply-defaults: alle Defaults erneut additiv (entzogene
                // Default-Rechte kommen zurueck, zusaetzliche bleiben).
                $zuVergeben = [];
                foreach ($katalog as $ressourceKey => $ressource) {
                    foreach ($ressource['defaults'][$rolleName] ?? [] as $aktion) {
                        $name = "{$ressourceKey}.{$aktion}";
                        if ($istNeu || $this->option('apply-defaults') || in_array($name, $neuePermissions, true)) {
                            $zuVergeben[] = $name;
                        }
                    }
                }

                if ($zuVergeben !== []) {
                    $rolle->givePermissionTo($zuVergeben);
                }
            }
        }

        $this->info($tenants->count() . ' Mandanten synchronisiert (' . count($systemRollen) . ' System-Rollen).');

        // ── 4. Verwaiste Permissions melden / loeschen ───────────────
        $verwaist = Permission::where('guard_name', 'web')
            ->where(fn ($q) => $q->where('name', 'like', 'partner.%')->orWhere('name', 'like', 'mde.%'))
            ->whereNotIn('name', $katalogPermissions)
            ->pluck('name');

        if ($verwaist->isNotEmpty()) {
            if ($this->option('prune')) {
                Permission::whereIn('name', $verwaist)->delete();
                $this->warn('Geloescht (--prune): ' . $verwaist->join(', '));
            } else {
                $this->warn($verwaist->count() . ' verwaiste Permissions (nicht mehr im Katalog):');
                $this->warn('  ' . $verwaist->join(', '));
                $this->warn('  Loeschen mit: php artisan rosi:permissions-sync --prune');
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->info('Sync abgeschlossen.');

        return self::SUCCESS;
    }
}
