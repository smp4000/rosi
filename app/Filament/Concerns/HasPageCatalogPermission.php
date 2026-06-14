<?php

namespace App\Filament\Concerns;

use Spatie\Permission\PermissionRegistrar;

/**
 * Zugriffspruefung fuer Filament-Pages auf Basis des Permission-Katalogs.
 *
 * Verwendung:
 *   use HasPageCatalogPermission;
 *   protected static string $accessPermission = 'partner.settings.manage';
 *
 * Ohne die Permission verschwindet die Seite aus der Navigation und
 * ist auch per URL nicht aufrufbar (403).
 */
trait HasPageCatalogPermission
{
    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user || empty(static::$accessPermission)) {
            return false;
        }

        // Inhaber (Partner) hat immer Zugriff (Sicherheitsnetz)
        if ($user->type === 'partner') {
            return true;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        try {
            return $user->hasPermissionTo(static::$accessPermission);
        } catch (\Throwable $e) {
            return false; // Permission (noch) nicht in der DB → kein Zugriff
        }
    }
}
