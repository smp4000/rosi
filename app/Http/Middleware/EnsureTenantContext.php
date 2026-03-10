<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: Stellt sicher dass der Tenant-Kontext gesetzt ist.
 * Setzt die tenant_id in der Session basierend auf dem authentifizierten Benutzer.
 * Super-Admins bekommen KEINE tenant_id gesetzt (sehen alle Daten ueber Filament).
 */
class EnsureTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Super-Admin gehoert ins Admin-Panel, nicht ins Partner-Panel
        if ($user->isSuperAdmin()) {
            return redirect()->route('filament.admin.pages.dashboard');
        }

        // Tenant-ID aus dem User-Model in die Session setzen
        if ($user->tenant_id && ! session()->has('tenant_id')) {
            session(['tenant_id' => $user->tenant_id]);
        }

        // Sicherheitscheck: Tenant-ID stimmt mit dem User ueberein
        if (session('tenant_id') && session('tenant_id') !== $user->tenant_id) {
            session(['tenant_id' => $user->tenant_id]);
        }

        // Sicherstellen dass der Mandant existiert und aktiv ist
        if (! $user->tenant_id) {
            abort(403, 'Kein Mandant zugeordnet. Bitte kontaktieren Sie den Administrator.');
        }

        return $next($request);
    }
}
