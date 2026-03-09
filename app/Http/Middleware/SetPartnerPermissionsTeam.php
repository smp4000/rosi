<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setzt die Team-ID fuer spatie/laravel-permission im Partner-Panel.
 * Partner-Rollen/Permissions sind unter der jeweiligen tenant_id gespeichert.
 */
class SetPartnerPermissionsTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenant_id) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
        }

        return $next($request);
    }
}
