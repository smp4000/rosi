<?php

namespace App\Filament\Concerns;

use Spatie\Permission\PermissionRegistrar;

/**
 * Generische Rechte-Pruefung fuer Filament-Resources auf Basis des
 * Permission-Katalogs (config/permission-katalog.php).
 *
 * Verwendung in einer Resource:
 *   use HasCatalogPermissions;
 *   protected static ?string $permissionKey = 'partner.employees';
 *
 * Die Standard-Aktionen (list/view/create/edit/delete) werden gegen den
 * Katalog geprueft. Hat eine Ressource eine Aktion nicht im Katalog
 * (z.B. Gutscheine nur 'list'), greifen sinnvolle Fallbacks — fuer
 * Schreib-Aktionen gilt dann: nicht erlaubt.
 *
 * Filament blendet Navigation und Buttons automatisch aus, wenn die
 * can*-Methoden false liefern.
 */
trait HasCatalogPermissions
{
    /** Hat der eingeloggte Benutzer diese Katalog-Permission? */
    protected static function katalogPermission(string $aktion, array $fallbacks = []): bool
    {
        $user = auth()->user();
        $key = static::$permissionKey ?? null;

        if (! $user || ! $key) {
            return false;
        }

        // Inhaber (Partner) hat IMMER vollen Zugriff — unabhaengig vom
        // Permission-Sync. Sicherheitsnetz gegen Selbst-Aussperrung.
        if ($user->type === 'partner') {
            return true;
        }

        // Achtung: Ressourcen-Keys enthalten Punkte (partner.gas-stations) —
        // deshalb NICHT per Dot-Notation in die Config greifen
        $ressource = config('permission-katalog.ressourcen', [])[$key] ?? [];
        $aktionen = array_keys($ressource['aktionen'] ?? []);

        // Gewuenschte Aktion — oder erster Fallback, den der Katalog kennt
        foreach (array_merge([$aktion], $fallbacks) as $kandidat) {
            if (in_array($kandidat, $aktionen, true)) {
                app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

                try {
                    return $user->hasPermissionTo("{$key}.{$kandidat}");
                } catch (\Throwable $e) {
                    return false; // Permission (noch) nicht in der DB → kein Zugriff
                }
            }
        }

        return false; // Aktion existiert fuer diese Ressource nicht
    }

    /**
     * Oeffentliche Pruefung einer beliebigen Katalog-Aktion (z.B. 'report',
     * 'download') — fuer eigene Buttons/Header-Actions in Pages.
     */
    public static function userCan(string $aktion, array $fallbacks = []): bool
    {
        return static::katalogPermission($aktion, $fallbacks);
    }

    public static function canViewAny(): bool
    {
        return static::katalogPermission('list', ['view', 'use', 'manage', 'logs']);
    }

    public static function canView($record): bool
    {
        return static::katalogPermission('view', ['list', 'use', 'manage', 'logs']);
    }

    public static function canCreate(): bool
    {
        return static::katalogPermission('create', ['import']);
    }

    public static function canEdit($record): bool
    {
        return static::katalogPermission('edit');
    }

    public static function canDelete($record): bool
    {
        return static::katalogPermission('delete');
    }

    /**
     * Feine Aktionen (Shield-Schema): Wenn die Ressource die Aktion im
     * Katalog definiert (z.B. 'delete-any', 'restore'), wird GENAU diese
     * geprueft. Ohne Definition greift der Fallback auf die Basis-Aktion.
     */
    public static function canDeleteAny(): bool
    {
        return static::katalogPermission('delete-any', ['delete']);
    }

    public static function canForceDelete($record): bool
    {
        return static::katalogPermission('force-delete', ['delete']);
    }

    public static function canForceDeleteAny(): bool
    {
        return static::katalogPermission('force-delete-any', ['force-delete', 'delete']);
    }

    public static function canRestore($record): bool
    {
        return static::katalogPermission('restore', ['delete']);
    }

    public static function canRestoreAny(): bool
    {
        return static::katalogPermission('restore-any', ['restore', 'delete']);
    }

    public static function canReplicate($record): bool
    {
        return static::katalogPermission('replicate', ['create']);
    }

    public static function canReorder(): bool
    {
        return static::katalogPermission('reorder', ['edit']);
    }
}
