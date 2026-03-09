<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: Prueft ob die Testphase des Mandanten abgelaufen ist.
 * Wenn ja, wird der Benutzer zur Abo-Seite weitergeleitet.
 * Super-Admins sind von dieser Pruefung ausgenommen.
 */
class CheckTrialExpired
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

        // Pruefen ob der Mandant Zugang hat (Trial aktiv oder Abo aktiv)
        if (! $tenant->hasAccess()) {
            // Trial abgelaufen und kein aktives Abo
            if ($tenant->isTrialExpired()) {
                // Weiterleitung zur Abo-/Upgrade-Seite
                return redirect()
                    ->route('subscription.choose')
                    ->with('warning', 'Ihre kostenlose Testphase ist abgelaufen. Bitte waehlen Sie ein Abonnement um fortzufahren.');
            }

            // Mandant deaktiviert
            if (! $tenant->is_active) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('login')
                    ->with('error', 'Ihr Zugang wurde deaktiviert. Bitte kontaktieren Sie den Support.');
            }
        }

        return $next($request);
    }
}
