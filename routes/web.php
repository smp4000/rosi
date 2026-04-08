<?php

use App\Http\Controllers\Auth\EmailVerificationController;
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
