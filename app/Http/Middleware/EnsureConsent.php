<?php

namespace App\Http\Middleware;

use App\Services\ConsentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: Prueft ob der Benutzer alle Pflicht-Einwilligungen
 * in der aktuellen Version akzeptiert hat.
 *
 * Wenn Einwilligungen fehlen (z.B. nach Versions-Update der
 * Datenschutzerklaerung), wird zur Einwilligungsseite weitergeleitet.
 */
class EnsureConsent
{
    public function __construct(
        protected ConsentService $consentService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Super-Admin ueberspringen (hat eigene Zugangsregeln)
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Pruefen ob alle Pflicht-Einwilligungen vorhanden sind
        if (! $this->consentService->hasAllRequiredConsents($user)) {
            $missing = $this->consentService->getMissingConsents($user);

            // Bereits auf der Einwilligungsseite? Nicht erneut umleiten
            if ($request->routeIs('consent.update')) {
                return $next($request);
            }

            // Fehlende Einwilligungen in Session speichern
            session(['missing_consents' => $missing]);

            // TODO: Zur Einwilligungsseite weiterleiten (wird spaeter erstellt)
            // return redirect()->route('consent.update');

            // Vorerst: Warnung in Session und weiter
            session()->flash('warning', 'Bitte aktualisieren Sie Ihre Datenschutz-Einwilligungen.');

            return $next($request);
        }

        return $next($request);
    }
}
