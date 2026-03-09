<?php

namespace App\Http\Middleware;

use Closure;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setzt die globale Team-ID fuer spatie/laravel-permission im Admin-Panel.
 * Super-Admin Rollen/Permissions sind unter der GLOBAL_TEAM_ID gespeichert.
 */
class SetAdminPermissionsTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSuperAdmin()) {
            app(PermissionRegistrar::class)->setPermissionsTeamId(
                RolesAndPermissionsSeeder::GLOBAL_TEAM_ID
            );
        }

        return $next($request);
    }
}
