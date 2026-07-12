<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\ShiftSettlementAttachmentController;
use App\Http\Controllers\TelegramWebhookController;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\VerifyEmail;
use App\Livewire\DocumentSignature;
use App\Livewire\Onboarding\OnboardingSuccess;
use App\Livewire\Onboarding\OnboardingWizard;
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

// Named Route fuer redirect()->route('dashboard') — leitet auf Filament Partner-Panel
Route::get('/dashboard-redirect', fn () => redirect('/dashboard'))->name('dashboard');

// Mitarbeiter-Onboarding (oeffentlich, Token-basiert)
Route::get('/onboarding/erfolg', OnboardingSuccess::class)->name('onboarding.success');
Route::get('/onboarding/{token}', OnboardingWizard::class)->name('onboarding');

// Dokument-Signatur (oeffentlich, Token-basiert — Mitarbeiter unterschreibt online)
Route::get('/dokument/unterschreiben/{token}', DocumentSignature::class)->name('document.sign');

// Telegram Webhook (oeffentlich, CSRF-exempt — Telegram ruft diesen Endpoint auf)
Route::post('/webhook/telegram/{tenantSlug}', [TelegramWebhookController::class, 'handle'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhook.telegram');

/*
|--------------------------------------------------------------------------
| Gast-Routen (nur fuer nicht-authentifizierte Benutzer)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => redirect('/dashboard'))->name('login');
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

    // --- ROSI-Print: enroll.json der Station (fuer Stations-Installer) ---
    // Liefert { server_url, enroll_token } als Datei (attachment). Der Browser
    // speichert sie per "Speichern unter" in den Ordner der EXE.
    Route::get('/print-agent-enroll/{station}', function (\App\Models\GasStation $station) {
        abort_unless($station->tenant_id === auth()->user()->tenant_id, 403);

        $json = json_encode([
            'server_url' => rtrim(config('app.url'), '/'),
            'enroll_token' => $station->ensureEnrollmentToken(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="enroll.json"',
        ]);
    })->name('print-agent.enroll');

    // --- ROSI-Print: generischer Setup-Installer (.cmd) ---
    // Installiert NUR das Geruest (EXE) in den gewaehlten Ordner (Standard
    // %LOCALAPPDATA%\RosiPrintAgent, auswaehlbar), Autostart, startet den Agent.
    // KEIN Token/keine Station — die Bindung erfolgt danach (Dashboard-Freigabe
    // nach Self-Register ODER enroll.json in den Ordner legen).
    Route::get('/print-agent-setup', function () {
        $latest = \App\Models\AppVersion::published()
            ->where('platform', 'print-agent')
            ->whereNotNull('apk_path')
            ->whereNotNull('version_code')
            ->orderByDesc('version_code')
            ->get()
            ->first(fn ($v) => $v->hasApk());

        $exeUrl = $latest
            ? route('api.v1.print.agent.version.download', ['version' => $latest->version])
            : '';

        // Reines Batch (kein PowerShell/Base64) — wird nicht vom Virenscanner als
        // verschleiertes Skript geflaggt. Download via curl (in Win10/11 enthalten).
        $tpl = <<<'CMD'
@echo off
title ROSI Print Setup
setlocal enabledelayedexpansion
echo(
echo  ============================================
echo    ROSI Print  -  Installation
echo  ============================================
echo(

if "{{EXE_URL}}"=="" (
  echo  Es wurde noch keine Agent-Version veroeffentlicht.
  echo  Bitte zuerst im Admin hochladen.
  echo(
  pause
  exit /b 1
)

set "DEST=%LOCALAPPDATA%\RosiPrintAgent"
set /p "DEST=Installationsordner [!DEST!]: "
if "!DEST!"=="" set "DEST=%LOCALAPPDATA%\RosiPrintAgent"

echo(
echo  Installiere nach: !DEST!
if not exist "!DEST!" mkdir "!DEST!"

taskkill /im RosiPrintAgent.exe /f >nul 2>&1

echo  Lade ROSI Print herunter (kann etwas dauern)...
curl -L -s -o "!DEST!\RosiPrintAgent.exe" "{{EXE_URL}}"

if not exist "!DEST!\RosiPrintAgent.exe" (
  echo  FEHLER: Download fehlgeschlagen. Internetverbindung pruefen.
  echo(
  pause
  exit /b 1
)

reg add "HKCU\Software\Microsoft\Windows\CurrentVersion\Run" /v RosiPrintAgent /t REG_SZ /d "\"!DEST!\RosiPrintAgent.exe\"" /f >nul

start "" "!DEST!\RosiPrintAgent.exe"
start "" "!DEST!"

echo(
echo  Fertig. ROSI Print laeuft und startet kuenftig automatisch mit.
echo  Naechster Schritt: im Dashboard freigeben ODER enroll.json
echo  in den Ordner legen.
echo(
pause
CMD;

        $cmd = str_replace(["\n", '{{EXE_URL}}'], ["\r\n", $exeUrl], $tpl);

        return response($cmd, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="ROSI-Print-Setup.cmd"',
        ]);
    })->name('print-agent.setup');

    // --- DYMO Druck-Labels aus Session holen (fuer Browser-JS) ---
    Route::get('/dymo/print-labels', function () {
        $labels = session()->pull('dymo_print_labels', []);
        return response()->json($labels);
    })->name('dymo.print-labels');

    // --- Print-Queue: Pending Jobs abrufen (Polling vom Browser) ---
    Route::get('/dymo/pending-jobs', function () {
        $tenantId = auth()->user()->tenant_id;
        // Nur Legacy-Jobs ohne Station (Browser-Druck). Jobs mit station_id
        // werden vom Stations-Agent (ROSI Print) geholt -> kein Doppeldruck.
        $jobs = \App\Models\PrintJob::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->whereNull('station_id')
            ->orderBy('created_at')
            ->get(['id', 'job_type', 'reference', 'payload', 'created_by', 'created_at']);
        return response()->json($jobs);
    })->name('dymo.pending-jobs');

    // --- Print-Queue: Job als erledigt markieren ---
    Route::post('/dymo/complete-job/{id}', function (string $id) {
        $tenantId = auth()->user()->tenant_id;
        $job = \App\Models\PrintJob::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        $job->update([
            'status' => 'done',
            'printed_at' => now(),
        ]);
        return response()->json(['success' => true]);
    })->name('dymo.complete-job');

    // --- Schichtabrechnung Anhaenge (gestreamt, kein storage:link noetig) ---
    Route::get('/shift-attachments/{settlement}/{type}/{returnId?}', [ShiftSettlementAttachmentController::class, 'show'])
        ->name('shift.attachment')
        ->where('type', 'cash_report|return');

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
    Route::middleware(['verified', 'tenant', 'trial'])->group(function () {
        // PDF-Vorschau fuer Dokumentvorlagen
        Route::get('/vorlage/{template}/vorschau', function (\App\Models\DocumentTemplate $template) {
            // Platzhalter mit Beispieldaten oder echten Firmendaten fuellen
            $tenantId = session('tenant_id');
            $tenant = \App\Models\Tenant::find($tenantId);

            // Beispiel-Mitarbeiter-Daten fuer Vorschau
            $previewVariables = [
                'mitarbeiter_name' => 'Max Mustermann',
                'mitarbeiter_vorname' => 'Max',
                'mitarbeiter_nachname' => 'Mustermann',
                'mitarbeiter_email' => 'max@beispiel.de',
                'mitarbeiter_strasse' => 'Musterstrasse 1',
                'mitarbeiter_plz' => '12345',
                'mitarbeiter_stadt' => 'Musterstadt',
                'mitarbeiter_adresse' => 'Musterstrasse 1, 12345 Musterstadt',
                'mitarbeiter_geburtsdatum' => '01.01.1990',
                'mitarbeiter_geburtsort' => 'Musterstadt',
                'mitarbeiter_sv_nummer' => '12 010190 M 001',
                'mitarbeiter_steuer_id' => '12345678901',
                'mitarbeiter_steuerklasse' => '1',
                'mitarbeiter_krankenkasse' => 'AOK',
                'beschaeftigungsart' => 'Vollzeit',
                'wochenstunden' => '40',
                'eintrittsdatum' => now()->format('d.m.Y'),
                'austrittsdatum' => '',
                'probezeit_ende' => now()->addMonths(6)->format('d.m.Y'),
                'urlaubstage' => '30',
                'gehalt' => '2.800,00',
                'gehaltsart' => 'monatlich',
                'arbeitgeber_name' => $tenant?->name ?? 'Mustermann GmbH',
                'arbeitgeber_strasse' => $tenant?->street ?? 'Hauptstrasse 1',
                'arbeitgeber_plz' => $tenant?->zip ?? '12345',
                'arbeitgeber_stadt' => $tenant?->city ?? 'Musterstadt',
                'arbeitgeber_adresse' => trim(($tenant?->street ?? 'Hauptstrasse 1') . ', ' . ($tenant?->zip ?? '12345') . ' ' . ($tenant?->city ?? 'Musterstadt')),
                'tankstelle_name' => 'ARAL Musterstadt',
                'tankstelle_adresse' => 'Tankstellenweg 5, 12345 Musterstadt',
                'datum_heute' => now()->format('d.m.Y'),
                'datum_jahr' => now()->format('Y'),
                'ort_datum' => ($tenant?->city ?? 'Musterstadt') . ', ' . now()->format('d.m.Y'),
            ];

            // Benutzerdefinierte Platzhalter dazu laden
            $customPlaceholders = \App\Models\PlaceholderSetting::getVariableMap($tenantId);
            $previewVariables = array_merge($previewVariables, $customPlaceholders);

            // Platzhalter ersetzen
            $content = $template->content;
            $headerHtml = $template->header_html ?? '';
            $footerHtml = $template->footer_html ?? '';

            foreach ($previewVariables as $key => $value) {
                $content = str_replace('{{' . $key . '}}', (string) ($value ?? ''), $content);
                $headerHtml = str_replace('{{' . $key . '}}', (string) ($value ?? ''), $headerHtml);
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.template-preview', [
                'title' => $template->name,
                'content' => $content,
                'headerHtml' => $headerHtml,
            ]);
            $pdf->setPaper('A4');

            return $pdf->stream($template->name . ' — Vorschau.pdf');
        })->name('template.preview');

        // Sammel-PDF (Druck) herunterladen
        Route::get('/sammel-pdf/{filename}', function (string $filename) {
            $filename = basename($filename);
            $path = storage_path('app/private/print_jobs/' . $filename);
            if (! file_exists($path)) {
                abort(404, 'Sammel-PDF nicht gefunden');
            }

            return response()->file($path, ['Content-Type' => 'application/pdf']);
        })->where('filename', '.*')->name('print-pdf.download');

        // Versand-Bericht herunterladen
        Route::get('/versand-bericht/{filename}', function (string $filename) {
            $filename = basename($filename);
            $path = storage_path('app/private/reports/' . $filename);
            if (! file_exists($path)) {
                abort(404, 'Versand-Bericht nicht gefunden');
            }

            return response()->download($path, $filename, ['Content-Type' => 'application/pdf']);
        })->where('filename', '.*')->name('report.download');

        // Mitarbeiter-Ausweis PDF drucken
        Route::get('/mitarbeiter/{user}/ausweis', function (\App\Models\User $user) {
            // Sicherstellen, dass der User zum Tenant gehoert
            $tenantId = session('tenant_id');
            if ($user->tenant_id !== $tenantId) {
                abort(403);
            }

            // Scan-Code pruefen
            if (! $user->scan_code) {
                abort(404, 'Kein Scan-Code vorhanden. Bitte zuerst einen Code generieren.');
            }

            // Zugewiesene Tankstellen laden
            $stations = $user->gasStations()->with('brand')->get();
            $allStationNames = $stations->pluck('name')->toArray();

            // QR-Code generieren
            $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::size(300)
                ->margin(1)
                ->generate($user->scan_code);
            $qrBase64 = base64_encode($qrSvg);

            // Badge pro Station erstellen
            $badges = [];
            if ($stations->isEmpty()) {
                // Kein Station zugewiesen — trotzdem einen Badge ohne Station
                $badges[] = [
                    'employee_name' => $user->name,
                    'station_name' => 'Keine Station zugewiesen',
                    'station_address' => '',
                    'tenant_name' => \App\Models\Tenant::find($tenantId)?->name ?? '',
                    'brand_name' => '',
                    'brand_logo' => null,
                    'scan_code' => $user->scan_code,
                    'qr_base64' => $qrBase64,
                    'all_stations' => [],
                    'created_at' => now()->format('d.m.Y'),
                ];
            } else {
                foreach ($stations as $station) {
                    $stationLogo = null;

                    // 1. Tankstellen-Logo (direkt am Station-Model)
                    if ($station->logo) {
                        $logoPath = storage_path('app/public/' . $station->logo);
                        if (file_exists($logoPath)) {
                            $mime = mime_content_type($logoPath);
                            $stationLogo = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
                        }
                    }

                    // 2. Fallback: Brand-Logo
                    if (! $stationLogo && $station->brand && $station->brand->logo) {
                        $logoPath = storage_path('app/public/' . $station->brand->logo);
                        if (file_exists($logoPath)) {
                            $mime = mime_content_type($logoPath);
                            $stationLogo = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
                        }
                    }

                    $address = trim(
                        ($station->street ?? '') . ' ' .
                        ($station->house_number ?? '') . ', ' .
                        ($station->zip ?? '') . ' ' .
                        ($station->city ?? '')
                    );

                    $badges[] = [
                        'employee_name' => $user->name,
                        'station_name' => $station->name,
                        'station_address' => $address,
                        'tenant_name' => \App\Models\Tenant::find($tenantId)?->name ?? '',
                        'brand_name' => $station->brand?->name ?? '',
                        'brand_logo' => $stationLogo,
                        'scan_code' => $user->scan_code,
                        'qr_base64' => $qrBase64,
                        'all_stations' => $allStationNames,
                        'created_at' => now()->format('d.m.Y'),
                    ];
                }
            }

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.employee-badge', [
                'badges' => $badges,
            ]);
            $pdf->setPaper('A4');

            $filename = 'Ausweis_' . str_replace(' ', '_', $user->name) . '.pdf';
            return $pdf->stream($filename);
        })->name('employee.badge');

        // Rechnungs-PDF anzeigen/downloaden
        Route::get('/rechnung/{invoice}/pdf', function (\App\Models\Invoice $invoice) {
            if (! $invoice->pdf_path || ! \Illuminate\Support\Facades\Storage::exists($invoice->pdf_path)) {
                abort(404, 'PDF nicht gefunden');
            }

            return response()->file(
                \Illuminate\Support\Facades\Storage::path($invoice->pdf_path),
                ['Content-Type' => 'application/pdf']
            );
        })->name('invoice.pdf');
    });

    // --- Abo-Seite (ohne Trial-Check, damit man hinkommt wenn Trial abgelaufen) ---
    Route::get('/abo', function () {
        return view('subscription.choose');
    })->name('subscription.choose');
});

/*
|--------------------------------------------------------------------------
| Cron-Trigger per URL (Hosting ohne Shell-Cron, z.B. All-Inkl KAS)
|--------------------------------------------------------------------------
| Der KAS-Cronjob ruft alle 5 Minuten diese URL auf:
|   https://rosi.aral-welle.com/cron-run/<CRON_TOKEN>
| Fuehrt den Laravel-Scheduler aus (Temperatur-Poll, Druck-Cleanup, DSGVO ...).
*/
Route::get('/cron-run/{token}', function (string $token) {
    $expected = config('app.cron_token');
    abort_unless($expected && hash_equals($expected, $token), 403);

    \Illuminate\Support\Facades\Artisan::call('schedule:run');

    return response('OK ' . now()->toDateTimeString() . "\n" . \Illuminate\Support\Facades\Artisan::output(), 200)
        ->header('Content-Type', 'text/plain');
})->name('cron.run');
