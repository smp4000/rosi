<?php

namespace App\Filament\Concerns;

use Spatie\Permission\PermissionRegistrar;

/**
 * Sichtbarkeit von Dashboard-Widgets ueber den Permission-Katalog.
 *
 * Verwendung:
 *   use HasWidgetCatalogPermission;
 *   protected static string $accessPermission = 'partner.dashboard.stats';
 *
 * Damit lassen sich Dashboard-Bausteine (Statistiken, Warnungen,
 * Schnellaktionen) pro Rolle einzeln in der Matrix schalten.
 */
trait HasWidgetCatalogPermission
{
    public static function canView(): bool
    {
        return static::katalogWidgetPermission();
    }

    protected static function katalogWidgetPermission(): bool
    {
        $user = auth()->user();
        if (! $user || empty(static::$accessPermission)) {
            return false;
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        try {
            return $user->hasPermissionTo(static::$accessPermission);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
