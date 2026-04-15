<?php

use App\Http\Controllers\Api\V1\AppVersionController;
use App\Http\Controllers\Api\V1\ArticleController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\FuelTheftController;
use App\Http\Controllers\Api\V1\MhdController;
use App\Http\Controllers\Api\V1\PrintController;
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

    // --- MHD-Kontrolle ---
    Route::get('/mhd', [MhdController::class, 'index'])
        ->name('api.v1.mhd.index');
    Route::get('/mhd/summary', [MhdController::class, 'summary'])
        ->name('api.v1.mhd.summary');
    Route::post('/mhd', [MhdController::class, 'store'])
        ->name('api.v1.mhd.store');
    Route::put('/mhd/{id}/extend', [MhdController::class, 'extend'])
        ->name('api.v1.mhd.extend');
    Route::post('/mhd/{id}/dispose', [MhdController::class, 'dispose'])
        ->name('api.v1.mhd.dispose');
    Route::delete('/mhd/{id}', [MhdController::class, 'destroy'])
        ->name('api.v1.mhd.destroy');

    // --- Drucker (oeffentlich, da DYMO nur lokal erreichbar) ---
    Route::get('/print/printers', [PrintController::class, 'printers'])
        ->name('api.v1.print.printers');
    Route::get('/print/status', [PrintController::class, 'status'])
        ->name('api.v1.print.status');
    Route::post('/print/test', [PrintController::class, 'testPrint'])
        ->name('api.v1.print.test');
    Route::post('/print/raw', [PrintController::class, 'printRaw'])
        ->name('api.v1.print.raw');
    Route::post('/print/template', [PrintController::class, 'printTemplate'])
        ->name('api.v1.print.template');
    Route::post('/print/render', [PrintController::class, 'renderTemplate'])
        ->name('api.v1.print.render');

    // --- Authentifizierung ---

    // Scan-Code fuer NFC-Tag-Beschriftung
    Route::get('/auth/employee-scan-code/{id}', [AuthController::class, 'employeeScanCode'])
        ->name('api.v1.auth.employee-scan-code');

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

        // --- Tankbetrug ---
        Route::get('/fuel-thefts/form-data', [FuelTheftController::class, 'formData'])
            ->name('api.v1.fuel-thefts.form-data');
        Route::post('/fuel-thefts', [FuelTheftController::class, 'store'])
            ->name('api.v1.fuel-thefts.store');

        // --- Drucken (Print-Gateway) ---
        Route::post('/print/label', [PrintController::class, 'printLabel'])
            ->name('api.v1.print.label');

        // Hier kommen spaeter weitere Phase 1 Routen:
        // - POST /v1/write-offs           → Abschriften
        // - POST /v1/incidents            → Stoerungen
        // - GET  /v1/shifts               → Schichtplan

    });

});
