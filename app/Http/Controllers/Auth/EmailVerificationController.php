<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Controller fuer E-Mail-Verifizierung.
 * Verarbeitet den Klick auf den Bestaetigungslink.
 */
class EmailVerificationController extends Controller
{
    /**
     * E-Mail als verifiziert markieren.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->route('dashboard')->with('success', 'Ihre E-Mail-Adresse wurde erfolgreich bestaetigt!');
    }
}
