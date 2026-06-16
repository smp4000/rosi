<?php

use App\Http\Controllers\Api\V1\AppVersionController;
use App\Http\Controllers\Api\V1\ArticleController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DepreciationController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\FuelTheftController;
use App\Http\Controllers\Api\V1\KioskController;
use App\Http\Controllers\Api\V1\MhdController;
use App\Http\Controllers\Api\V1\NfcAdminController;
use App\Http\Controllers\Api\V1\PrintController;
use App\Http\Controllers\Api\V1\PrintAgentController;
use App\Http\Controllers\Api\V1\ShiftSettlementController;
use App\Http\Controllers\Api\V1\VoucherController;
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

    // --- In-App-Updater: neueste Version + APK-Download ---
    Route::get('/app-version/latest', [AppVersionController::class, 'latest'])
        ->name('api.v1.app-version.latest');
    Route::get('/app-version/download/{version}', [AppVersionController::class, 'download'])
        ->name('api.v1.app-version.download');

    // --- Geraete-Registrierung ---

    // QR-Code: App scannt Setup-QR vom Admin
    Route::post('/devices/register', [DeviceController::class, 'register'])
        ->name('api.v1.devices.register');

    // Stations-QR: MDE meldet sich direkt an der Tankstelle an (permanenter Code + GPS-Pruefung)
    Route::post('/devices/register-station', [DeviceController::class, 'registerStation'])
        ->name('api.v1.devices.register-station');

    // Einladungslink: Info abrufen (bevor MA sich registriert)
    Route::get('/devices/invite/{token}/info', [DeviceController::class, 'inviteInfo'])
        ->name('api.v1.devices.invite.info');

    // Einladungslink: Annehmen + PIN setzen
    Route::post('/devices/invite/accept', [DeviceController::class, 'acceptInvite'])
        ->name('api.v1.devices.invite.accept');

    // Geraet pruefen: Ist das Geraet noch registriert und aktiv?
    Route::get('/devices/verify', [DeviceController::class, 'verify'])
        ->name('api.v1.devices.verify');

    // --- Authentifizierung (OHNE Abo-Pruefung, muss immer funktionieren) ---

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
    // Daten-Routen (oeffentlich, aber mit device_token identifizierbar)
    // ------------------------------------------------------------------

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
    Route::get('/print/destinations', [PrintController::class, 'destinations'])
        ->name('api.v1.print.destinations');
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

    // --- Druck-Agent (Tray-App am Stations-PC, Auth per Agent-Token) ---
    Route::post('/print/agent/heartbeat', [PrintAgentController::class, 'heartbeat'])
        ->name('api.v1.print.agent.heartbeat');
    Route::post('/print/agent/jobs/claim', [PrintAgentController::class, 'claim'])
        ->name('api.v1.print.agent.claim');
    Route::post('/print/agent/jobs/{id}/ack', [PrintAgentController::class, 'ack'])
        ->name('api.v1.print.agent.ack');

    // Scan-Code fuer NFC-Tag-Beschriftung
    Route::get('/auth/employee-scan-code/{id}', [AuthController::class, 'employeeScanCode'])
        ->name('api.v1.auth.employee-scan-code');

    // --- Schichtabrechnung (oeffentlich, nur device_token) ---
    Route::get('/shift-settlements/check-questions', [ShiftSettlementController::class, 'checkQuestions'])
        ->name('api.v1.shift-settlements.check-questions');
    Route::get('/shift-settlements/return-reasons', [ShiftSettlementController::class, 'returnReasons'])
        ->name('api.v1.shift-settlements.return-reasons');
    Route::get('/shift-settlements/last-values', [ShiftSettlementController::class, 'lastValues'])
        ->name('api.v1.shift-settlements.last-values');

    // --- Admin-Bereich der App: NFC-Chips beschreiben ---
    // Persoenliche Anmeldung (PIN + Permission mde.admin.nfc-write) + Audit
    Route::get('/admin/authorized-users', [NfcAdminController::class, 'authorizedUsers'])
        ->name('api.v1.admin.authorized-users');
    Route::post('/admin/authenticate', [NfcAdminController::class, 'authenticate'])
        ->name('api.v1.admin.authenticate');
    Route::post('/admin/nfc-written', [NfcAdminController::class, 'logNfcWritten'])
        ->name('api.v1.admin.nfc-written');
    Route::get('/admin/stations', [NfcAdminController::class, 'stations'])
        ->name('api.v1.admin.stations');
    Route::get('/admin/station-employees', [NfcAdminController::class, 'stationEmployees'])
        ->name('api.v1.admin.station-employees');

    // --- Abschriften (oeffentlich, nur device_token) ---
    Route::get('/depreciation-reasons', [DepreciationController::class, 'reasons'])
        ->name('api.v1.depreciation-reasons');

    // --- Gutscheine (oeffentlich, nur device_token) ---
    Route::get('/vouchers/lookup', [VoucherController::class, 'lookup'])
        ->name('api.v1.vouchers.lookup');
    Route::post('/vouchers/check-group', [VoucherController::class, 'checkGroup'])
        ->name('api.v1.vouchers.check-group');
    Route::get('/vouchers/reprint-counts', [VoucherController::class, 'reprintCounts'])
        ->name('api.v1.vouchers.reprint-counts');
    Route::get('/vouchers/by-group', [VoucherController::class, 'byGroup'])
        ->name('api.v1.vouchers.by-group');

    // --- Adress-Etiketten (oeffentlich, nur device_token: Lesen + Suche) ---
    Route::get('/address-labels', [\App\Http\Controllers\Api\V1\AddressLabelController::class, 'index'])
        ->name('api.v1.address-labels.index');
    Route::get('/address-labels/stations', [\App\Http\Controllers\Api\V1\AddressLabelController::class, 'stations'])
        ->name('api.v1.address-labels.stations');
    Route::get('/address-labels/search', [\App\Http\Controllers\Api\V1\AddressLabelController::class, 'search'])
        ->name('api.v1.address-labels.search');

    // --- Kiosk: Health + Artikel-Lookup (oeffentlich, nur device_token) ---
    Route::get('/kiosk/ping', [KioskController::class, 'ping'])
        ->name('api.v1.kiosk.ping');
    Route::get('/kiosk/articles/lookup', [KioskController::class, 'lookupByEan'])
        ->name('api.v1.kiosk.articles.lookup');
    Route::get('/kiosk/articles/by-objekt', [KioskController::class, 'lookupByObjekt'])
        ->name('api.v1.kiosk.articles.by-objekt');
    Route::post('/kiosk/articles/upsert-pending', [KioskController::class, 'upsertPending'])
        ->name('api.v1.kiosk.articles.upsert-pending');

    // ------------------------------------------------------------------
    // Geschuetzte Routen (Sanctum Session-Token + Abo-Pruefung)
    // ------------------------------------------------------------------

    Route::middleware(['auth:sanctum', 'api.access'])->group(function () {

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
            ->middleware('mde.permission:mde.fuel-theft.report')
            ->name('api.v1.fuel-thefts.store');

        // --- Drucken (Print-Gateway) ---
        Route::post('/print/label', [PrintController::class, 'printLabel'])
            ->name('api.v1.print.label');

        // --- Schichtabrechnung ---
        Route::post('/shift-settlements/start', [ShiftSettlementController::class, 'start'])
            ->middleware('mde.permission:mde.shift-settlement.own')
            ->name('api.v1.shift-settlements.start');
        Route::get('/shift-settlements/active', [ShiftSettlementController::class, 'active'])
            ->name('api.v1.shift-settlements.active');
        Route::post('/shift-settlements/{id}/safe-deposits', [ShiftSettlementController::class, 'addSafeDeposit'])
            ->name('api.v1.shift-settlements.safe-deposits');
        Route::post('/shift-settlements/{id}/returns', [ShiftSettlementController::class, 'addReturn'])
            ->name('api.v1.shift-settlements.returns');
        Route::post('/shift-settlements/{id}/complete', [ShiftSettlementController::class, 'complete'])
            ->middleware('mde.permission:mde.shift-settlement.own')
            ->name('api.v1.shift-settlements.complete');
        Route::get('/shift-settlements/mine', [ShiftSettlementController::class, 'mine'])
            ->name('api.v1.shift-settlements.mine');
        Route::get('/shift-settlements/{id}/details', [ShiftSettlementController::class, 'details'])
            ->name('api.v1.shift-settlements.details');
        Route::post('/shift-settlements/{id}/comments', [ShiftSettlementController::class, 'addComment'])
            ->name('api.v1.shift-settlements.comments');

        // --- Abschriften (geschuetzt) ---
        Route::post('/depreciations', [DepreciationController::class, 'store'])
            ->middleware('mde.permission:mde.writeoffs.record')
            ->name('api.v1.depreciations.store');

        // --- Gutscheine (geschuetzt) ---
        Route::post('/vouchers/generate', [VoucherController::class, 'generate'])
            ->middleware('mde.permission:mde.vouchers.issue')
            ->name('api.v1.vouchers.generate');
        Route::post('/vouchers/redeem', [VoucherController::class, 'redeem'])
            ->middleware('mde.permission:mde.vouchers.redeem')
            ->name('api.v1.vouchers.redeem');
        Route::post('/vouchers/reprint', [VoucherController::class, 'reprint'])
            ->middleware('mde.permission:mde.vouchers.reprint')
            ->name('api.v1.vouchers.reprint');

        // --- Adress-Etiketten (geschuetzt: anlegen + drucken) ---
        Route::post('/address-labels', [\App\Http\Controllers\Api\V1\AddressLabelController::class, 'store'])
            ->name('api.v1.address-labels.store');
        Route::post('/address-labels/{id}/print', [\App\Http\Controllers\Api\V1\AddressLabelController::class, 'reprint'])
            ->name('api.v1.address-labels.print');

        // --- Kiosk: Bewegungen (geschuetzt) ---
        Route::post('/kiosk/deliveries/save', [KioskController::class, 'saveDelivery'])
            ->middleware('mde.permission:mde.newspapers.delivery')
            ->name('api.v1.kiosk.deliveries.save');
        Route::post('/kiosk/remissions/save', [KioskController::class, 'saveRemission'])
            ->middleware('mde.permission:mde.newspapers.remission')
            ->name('api.v1.kiosk.remissions.save');
        Route::post('/kiosk/inventory/save', [KioskController::class, 'saveInventory'])
            ->middleware('mde.permission:mde.newspapers.inventory')
            ->name('api.v1.kiosk.inventory.save');

    });

});
