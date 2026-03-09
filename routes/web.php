<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\VerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Oeffentliche Routen
|--------------------------------------------------------------------------
*/

// Landing Page (wird in Schritt 5 erstellt)
Route::get('/', function () {
    return view('welcome');
})->name('home');

/*
|--------------------------------------------------------------------------
| Gast-Routen (nur fuer nicht-authentifizierte Benutzer)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/registrieren', Register::class)->name('register');
    Route::get('/passwort-vergessen', ForgotPassword::class)->name('password.request');
    Route::get('/passwort-zuruecksetzen/{token}', ResetPassword::class)->name('password.reset');
});

/*
|--------------------------------------------------------------------------
| Authentifizierte Routen
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    // --- E-Mail-Verifizierung ---
    Route::get('/email/verifizieren', VerifyEmail::class)
        ->name('verification.notice');

    Route::get('/email/verifizieren/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // --- Logout ---
    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('home');
    })->name('logout');

    // --- Geschuetzte Routen (verifiziert + Tenant + Trial) ---
    // Dashboard wird jetzt vom Filament Partner-Panel unter /dashboard bereitgestellt.
    // Weitere geschuetzte Routen (nicht-Filament) koennen hier hinzugefuegt werden.
    Route::middleware(['verified', 'tenant', 'trial'])->group(function () {
        // Platzhalter fuer zukuenftige Nicht-Filament-Routen
    });

    // --- Abo-Seite (ohne Trial-Check, damit man hinkommt wenn Trial abgelaufen) ---
    Route::get('/abo', function () {
        return view('subscription.choose');
    })->name('subscription.choose');
});
