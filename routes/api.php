<?php

use App\Http\Controllers\Api\V1\AppVersionController;
use App\Http\Controllers\Api\V1\ArticleController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| POS App API - Version 1
|--------------------------------------------------------------------------
|
| Alle Routen hier haben automatisch das Praefix /api/v1/
| Beispiel: POST /api/v1/auth/login
|
*/

Route::prefix('v1')->group(function () {

    // ------------------------------------------------------------------
    // Oeffentliche Routen (kein Token noetig)
    // ------------------------------------------------------------------

    // Health-Check: Ist die API erreichbar?
    Route::get('/ping', function () {
        return response()->json([
            'success' => true,
            'message' => 'ROSI POS API v1',
            'timestamp' => now()->toIso8601String(),
        ]);
    })->name('api.v1.ping');

    // --- App-Versionshistorie ---
    Route::get('/app-versions', [AppVersionController::class, 'index'])
        ->name('api.v1.app-versions');

    // --- Geraete-Registrierung ---

    // QR-Code: App scannt Setup-QR vom Admin
    Route::post('/devices/register', [DeviceController::class, 'register'])
        ->name('api.v1.devices.register');

    // Einladungslink: Info abrufen (bevor MA sich registriert)
    Route::get('/devices/invite/{token}/info', [DeviceController::class, 'inviteInfo'])
        ->name('api.v1.devices.invite.info');

    // Einladungslink: Annehmen + PIN setzen
    Route::post('/devices/invite/accept', [DeviceController::class, 'acceptInvite'])
        ->name('api.v1.devices.invite.accept');

    // Geraet pruefen: Ist das Geraet noch registriert und aktiv?
    Route::get('/devices/verify', [DeviceController::class, 'verify'])
        ->name('api.v1.devices.verify');

    // --- Artikelsuche (EAN, Artikelnummer, Text) ---
    Route::get('/articles/search', [ArticleController::class, 'search'])
        ->name('api.v1.articles.search');

    // --- Authentifizierung ---

    // Mitarbeiter-Login (PIN + Device-Token)
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->name('api.v1.auth.login');

    // Scan-Login: Code (Scanner/NFC/Kamera) + Device-Token
    Route::post('/auth/scan-login', [AuthController::class, 'scanLogin'])
        ->name('api.v1.auth.scan-login');

    // Mitarbeiterliste einer Station (fuer Login-Screen)
    Route::get('/auth/station-employees', [AuthController::class, 'stationEmployees'])
        ->name('api.v1.auth.station-employees');

    // ------------------------------------------------------------------
    // Geschuetzte Routen (Sanctum Session-Token noetig)
    // ------------------------------------------------------------------

    Route::middleware('auth:sanctum')->group(function () {

        // Wer bin ich?
        Route::get('/me', function (Request $request) {
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $request->user()->only(['id', 'name', 'email', 'type']),
                ],
            ]);
        })->name('api.v1.me');

        // Abmelden
        Route::post('/auth/logout', [AuthController::class, 'logout'])
            ->name('api.v1.auth.logout');

        // PIN aendern
        Route::put('/auth/pin', [AuthController::class, 'changePin'])
            ->name('api.v1.auth.pin');

        // Hier kommen spaeter Phase 1 Routen:
        // - POST /v1/mhd-checks          → MHD-Kontrolle
        // - POST /v1/write-offs           → Abschriften
        // - POST /v1/incidents            → Stoerungen
        // - GET  /v1/shifts               → Schichtplan

    });

});
