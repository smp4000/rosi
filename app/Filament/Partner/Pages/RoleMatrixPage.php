<?php

namespace App\Filament\Partner\Pages;

use App\Models\EmployeeStationRole;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rollen-Matrix: Berechtigungen pro Rolle als klickbare Checkbox-Matrix.
 *
 * Zeilen  = Ressourcen/Aktionen aus config/permission-katalog.php
 * Spalten = Rollen des Mandanten (System-Rollen 🔒 + eigene Rollen)
 *
 * Die Rolle "partner" (Inhaber) hat immer alle Rechte und wird
 * deshalb nicht als Spalte angezeigt.
 */
class RoleMatrixPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Rollen & Rechte';

    protected static ?string $title = 'Rollen & Rechte';

    protected static ?string $slug = 'rollen-rechte';

    protected static string|\UnitEnum|null $navigationGroup = 'Einstellungen';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.partner.pages.role-matrix';

    /** role_id => [permission-Namen] — Zustand der Matrix */
    public array $matrix = [];

    /** Nur sichtbar mit partner.roles.manage (Inhaber hat sie immer) */
    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        return $user->hasPermissionTo('partner.roles.manage');
    }

    public function mount(): void
    {
        $this->ladeMatrix();
    }

    // ── Daten fuer die View ───────────────────────────────────────

    /** Katalog gruppiert nach Bereich (dashboard, mde) */
    #[Computed]
    public function katalog(): array
    {
        return config('permission-katalog.ressourcen', []);
    }

    #[Computed]
    public function bereiche(): array
    {
        return config('permission-katalog.bereiche', []);
    }

    /** Anzeigbare Rollen des Mandanten (ohne Inhaber-Rolle) */
    #[Computed]
    public function rollen(): Collection
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(auth()->user()->tenant_id);

        return Role::where('tenant_id', auth()->user()->tenant_id)
            ->where('name', '!=', 'partner')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
    }

    private function ladeMatrix(): void
    {
        $this->matrix = [];
        foreach ($this->rollen as $rolle) {
            $this->matrix[$rolle->id] = $rolle->permissions->pluck('name')->all();
        }
    }

    // ── Aktionen ──────────────────────────────────────────────────

    /** Einzelne Checkbox umschalten */
    public function togglePermission(int $rolleId, string $permission): void
    {
        $rolle = $this->findeRolle($rolleId);
        if (! $rolle) {
            return;
        }

        if (in_array($permission, $this->matrix[$rolleId] ?? [], true)) {
            $rolle->revokePermissionTo($permission);
            $this->matrix[$rolleId] = array_values(array_diff($this->matrix[$rolleId], [$permission]));
        } else {
            $rolle->givePermissionTo($permission);
            $this->matrix[$rolleId][] = $permission;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Ganze Ressource (alle Aktionen) fuer eine Rolle an/aus */
    public function toggleRessource(int $rolleId, string $ressourceKey): void
    {
        $rolle = $this->findeRolle($rolleId);
        $ressource = $this->katalog[$ressourceKey] ?? null;
        if (! $rolle || ! $ressource) {
            return;
        }

        $alle = array_map(fn ($a) => "{$ressourceKey}.{$a}", array_keys($ressource['aktionen']));
        $vorhanden = array_intersect($alle, $this->matrix[$rolleId] ?? []);

        if (count($vorhanden) === count($alle)) {
            // Alles an → alles aus
            $rolle->revokePermissionTo($alle);
            $this->matrix[$rolleId] = array_values(array_diff($this->matrix[$rolleId], $alle));
        } else {
            $rolle->givePermissionTo($alle);
            $this->matrix[$rolleId] = array_values(array_unique(array_merge($this->matrix[$rolleId] ?? [], $alle)));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Eigene Rolle anlegen */
    public function rolleAnlegen(string $name): void
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 50) {
            Notification::make()->title('Bitte einen Namen (max. 50 Zeichen) angeben.')->danger()->send();
            return;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId(auth()->user()->tenant_id);

        if (Role::where('tenant_id', auth()->user()->tenant_id)->where('name', $name)->exists()) {
            Notification::make()->title("Rolle \"{$name}\" existiert bereits.")->danger()->send();
            return;
        }

        Role::create(['name' => $name, 'guard_name' => 'web']);
        $this->ladeMatrix();

        Notification::make()
            ->title("Rolle \"{$name}\" angelegt.")
            ->body('Neue Rollen starten ohne Rechte — Haekchen setzen zum Freischalten.')
            ->success()->send();
    }

    /** Eigene Rolle loeschen (System-Rollen sind geschuetzt) */
    public function rolleLoeschen(int $rolleId): void
    {
        $rolle = $this->findeRolle($rolleId);
        if (! $rolle) {
            return;
        }

        if ($rolle->is_system) {
            Notification::make()->title('System-Rollen koennen nicht geloescht werden.')->danger()->send();
            return;
        }

        $inVerwendung = EmployeeStationRole::withoutTenantScope()
            ->where('role_id', $rolle->id)->count();

        if ($inVerwendung > 0) {
            Notification::make()
                ->title('Rolle ist noch zugewiesen.')
                ->body("{$inVerwendung} Mitarbeiter-Zuweisungen entfernen, dann loeschen.")
                ->danger()->send();
            return;
        }

        $name = $rolle->name;
        $rolle->delete();
        $this->ladeMatrix();

        Notification::make()->title("Rolle \"{$name}\" geloescht.")->success()->send();
    }

    private function findeRolle(int $rolleId): ?Role
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(auth()->user()->tenant_id);

        return Role::where('id', $rolleId)
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where('name', '!=', 'partner') // Inhaber-Rolle nie ueber die Matrix aendern
            ->first();
    }
}
