<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: Prueft ob der Mandant ein aktives Abonnement hat.
 * Wird fuer Premium-Features verwendet die nur mit aktivem Abo zugaenglich sind.
 * Trial-Benutzer haben eingeschraenkten Zugang.
 */
class CheckSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if (! $tenant) {
            return $next($request);
        }

        // Pruefen ob der Mandant ein aktives Abo hat (nicht nur Trial)
        if (! $tenant->hasActiveSubscription()) {
            return redirect()
                ->route('subscription.choose')
                ->with('info', 'Diese Funktion erfordert ein aktives Abonnement.');
        }

        return $next($request);
    }
}
