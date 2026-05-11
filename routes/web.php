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

    // --- DYMO Druck-Labels aus Session holen (fuer Browser-JS) ---
    Route::get('/dymo/print-labels', function () {
        $labels = session()->pull('dymo_print_labels', []);
        return response()->json($labels);
    })->name('dymo.print-labels');

    // --- Print-Queue: Pending Jobs abrufen (Polling vom Browser) ---
    Route::get('/dymo/pending-jobs', function () {
        $tenantId = auth()->user()->tenant_id;
        $jobs = \App\Models\PrintJob::where('tenant_id', $tenantId)
            ->where('status', 'pending')
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

// --- Temporaere Fix-Route: Tenant auf Active setzen ---
// ENTFERNEN nach Fix!
Route::get('/debug/fix-tenant/{token}', function (string $token) {
    if ($token !== 'rosi2026debug') {
        abort(404);
    }

    $count = \App\Models\Tenant::query()
        ->where('subscription_status', '!=', 'active')
        ->update([
            'subscription_status' => 'active',
            'subscription_plan' => 'premium',
        ]);

    $tenants = \App\Models\Tenant::all(['id', 'name', 'subscription_status', 'subscription_plan', 'is_active']);

    return response()->json([
        'message' => $count . ' Tenant(s) auf active gesetzt.',
        'tenants' => $tenants,
    ]);
});

// --- Temporaer: Alle Caches leeren ---
// ENTFERNEN nach Debugging!
Route::get('/debug/clear-cache/{token}', function (string $token) {
    if ($token !== 'rosi2026debug') {
        abort(404);
    }
    \Illuminate\Support\Facades\Artisan::call('view:clear');
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    \Illuminate\Support\Facades\Artisan::call('config:clear');
    return response()->json([
        'message' => 'Alle Caches geleert (views, cache, config).',
    ]);
});

// --- Temporaer: Permissions debuggen und fixen ---
// ENTFERNEN nach Debugging!
Route::get('/debug/fix-permissions/{token}', function (string $token) {
    if ($token !== 'rosi2026debug') {
        abort(404);
    }

    try {
        $result = [];

        // 1. ALLE Caches leeren
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $result['cache'] = 'Alle Caches geleert';

        // 2. Team-Scope auf GLOBAL setzen
        $globalTeam = \Database\Seeders\RolesAndPermissionsSeeder::GLOBAL_TEAM_ID;
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($globalTeam);
        $result['team_id'] = $globalTeam;

        // 3. Tabellenstruktur pruefen
        $mhrColumns = \DB::select("SHOW COLUMNS FROM model_has_roles");
        $result['model_has_roles_columns'] = array_map(fn($c) => $c->Field, $mhrColumns);

        $mhpColumns = \DB::select("SHOW COLUMNS FROM model_has_permissions");
        $result['model_has_permissions_columns'] = array_map(fn($c) => $c->Field, $mhpColumns);

        // 4. Was steht in der DB?
        $roles = \DB::table('roles')->get();
        $result['db_roles'] = $roles->toArray();

        $modelRoles = \DB::table('model_has_roles')->get();
        $result['db_model_has_roles'] = $modelRoles->toArray();

        $result['db_permissions_count'] = \DB::table('permissions')->count();

        // 5. Admin-User
        $admin = \App\Models\User::where('type', 'super_admin')->first();
        if ($admin) {
            $result['admin_email'] = $admin->email;
            $result['admin_id'] = $admin->id;
            $result['admin_spatie_roles'] = $admin->getRoleNames()->toArray();
            $result['admin_can_edit'] = $admin->can('admin.tenants.edit');
            $result['admin_all_permissions'] = $admin->getAllPermissions()->pluck('name')->toArray();
        }

        return response()->json(['success' => true] + $result, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getFile() . ':' . $e->getLine(),
        ], 500, [], JSON_PRETTY_PRINT);
    }
});

// --- Temporaer: User-Liste anzeigen ---
// ENTFERNEN nach Debugging!
Route::get('/debug/users/{token}', function (string $token) {
    if ($token !== 'rosi2026debug') {
        abort(404);
    }

    $users = \App\Models\User::select('id', 'first_name', 'last_name', 'email', 'type', 'tenant_id', 'is_active')
        ->get()
        ->map(function ($u) {
            return [
                'name' => trim($u->first_name . ' ' . $u->last_name),
                'email' => $u->email,
                'type' => $u->type,
                'is_super_admin' => $u->isSuperAdmin(),
                'tenant_id' => $u->tenant_id,
                'active' => $u->is_active,
            ];
        });

    $currentUser = auth()->check() ? [
        'email' => auth()->user()->email,
        'type' => auth()->user()->type,
        'is_super_admin' => auth()->user()->isSuperAdmin(),
    ] : 'Nicht eingeloggt';

    return response()->json([
        'current_user' => $currentUser,
        'all_users' => $users,
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
});

// --- Temporaer APP_DEBUG einschalten ---
// ENTFERNEN nach Debugging!
Route::get('/debug/enable/{token}', function (string $token) {
    if ($token !== 'rosi2026debug') {
        abort(404);
    }

    // .env Datei lesen und APP_DEBUG auf true setzen
    $envPath = base_path('.env');
    $env = file_get_contents($envPath);
    $env = preg_replace('/^APP_DEBUG=.*/m', 'APP_DEBUG=true', $env);
    file_put_contents($envPath, $env);

    // Config-Cache leeren
    \Illuminate\Support\Facades\Artisan::call('config:clear');

    return response()->json(['message' => 'APP_DEBUG aktiviert. Fehler werden jetzt im Browser angezeigt. NICHT VERGESSEN: /debug/disable/rosi2026debug aufrufen!']);
});

Route::get('/debug/disable/{token}', function (string $token) {
    if ($token !== 'rosi2026debug') {
        abort(404);
    }

    $envPath = base_path('.env');
    $env = file_get_contents($envPath);
    $env = preg_replace('/^APP_DEBUG=.*/m', 'APP_DEBUG=false', $env);
    file_put_contents($envPath, $env);

    \Illuminate\Support\Facades\Artisan::call('config:clear');

    return response()->json(['message' => 'APP_DEBUG deaktiviert.']);
});

// --- Temporaere Debug-Route: System-Diagnose ---
// ENTFERNEN nach Debugging!
Route::get('/debug/last-errors/{token}', function (string $token) {
    if ($token !== 'rosi2026debug') {
        abort(404);
    }

    $out = [];
    $out[] = '=== ROSI System-Diagnose ===';
    $out[] = 'Zeit: ' . now()->toDateTimeString();
    $out[] = 'PHP: ' . PHP_VERSION;
    $out[] = 'Laravel: ' . app()->version();
    $out[] = 'APP_ENV: ' . config('app.env');
    $out[] = 'APP_DEBUG: ' . (config('app.debug') ? 'true' : 'false');
    $out[] = '';

    // Tenant-Status
    $out[] = '=== TENANTS ===';
    $tenants = \App\Models\Tenant::all();
    foreach ($tenants as $t) {
        $out[] = $t->name . ' | status=' . $t->subscription_status
            . ' | plan=' . ($t->subscription_plan ?? 'NULL')
            . ' | trial_ends=' . ($t->trial_ends_at ?? 'NULL')
            . ' | active=' . ($t->is_active ? 'ja' : 'nein')
            . ' | hasAccess=' . ($t->hasAccess() ? 'JA' : 'NEIN');
    }
    $out[] = '';

    // Filament Assets pruefen
    $out[] = '=== FILAMENT ASSETS ===';
    $filamentFiles = [
        'public/js/filament/filament/app.js',
        'public/css/filament/filament/app.css',
        'public/js/filament/schemas/schemas.js',
        'public/js/filament/actions/actions.js',
        'public/js/filament/tables/tables.js',
        'public/js/filament/forms/components/select.js',
        'public/js/filament/forms/components/date-time-picker.js',
    ];
    foreach ($filamentFiles as $f) {
        $path = base_path($f);
        if (file_exists($path)) {
            $out[] = '✓ ' . $f . ' (' . filesize($path) . ' Bytes)';
        } else {
            $out[] = '✗ FEHLT: ' . $f;
        }
    }
    $out[] = '';

    // Vite Build pruefen
    $out[] = '=== VITE BUILD ===';
    $viteFiles = ['public/build/manifest.json', 'public/build/assets'];
    foreach ($viteFiles as $f) {
        $path = base_path($f);
        $out[] = (file_exists($path) ? '✓' : '✗ FEHLT') . ' ' . $f;
    }
    $out[] = '';

    // Route-Cache
    $out[] = '=== CACHES ===';
    $out[] = 'Route-Cache: ' . (file_exists(base_path('bootstrap/cache/routes-v7.php')) ? 'AKTIV' : 'nicht aktiv');
    $out[] = 'Config-Cache: ' . (file_exists(base_path('bootstrap/cache/config.php')) ? 'AKTIV' : 'nicht aktiv');
    $out[] = '';

    // Logdatei
    $out[] = '=== LOG ===';
    $logFile = storage_path('logs/laravel.log');
    if (! file_exists($logFile)) {
        $out[] = 'Logdatei existiert nicht';
    } elseif (filesize($logFile) === 0) {
        $out[] = 'Logdatei ist leer';
    } else {
        $content = file_get_contents($logFile);
        $out[] = 'Logdatei: ' . filesize($logFile) . ' Bytes';
        $out[] = '';
        $out[] = substr($content, -5000);
    }

    return response('<pre>' . e(implode("\n", $out)) . '</pre>')
        ->header('Content-Type', 'text/html');
});
