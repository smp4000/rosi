<?php

namespace App\Providers;

use App\Listeners\LogFailedLogin;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use App\Models\SystemSetting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Zentraler Application Service Provider.
 * Registriert Event-Listener, Singletons und Boot-Logik.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Dienste registrieren.
     */
    public function register(): void
    {
        //
    }

    /**
     * System-SMTP aus system_settings laden und als Default-Mailer setzen.
     * Ueberschreibt die .env-Werte, sodass alle Laravel-Mails
     * (Verifizierung, Passwort-Reset, Notifications) ueber den Admin-SMTP gehen.
     */
    private function configureSystemMailer(): void
    {
        try {
            // Nur wenn Tabelle existiert (verhindert Fehler bei frischer Installation / Migration)
            if (! \Illuminate\Support\Facades\Schema::hasTable('system_settings')) {
                return;
            }

            $host = SystemSetting::get('smtp_host', null, 'mail');

            if (! $host) {
                return;
            }

            $encryption = SystemSetting::get('smtp_encryption', 'tls', 'mail');

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => (int) SystemSetting::get('smtp_port', '587', 'mail'),
                'mail.mailers.smtp.username' => SystemSetting::get('smtp_username', '', 'mail'),
                'mail.mailers.smtp.password' => SystemSetting::get('smtp_password', '', 'mail'),
                'mail.mailers.smtp.encryption' => in_array($encryption, ['ssl', 'SSL/TLS']) ? 'ssl' : 'tls',
                'mail.from.address' => SystemSetting::get('mail_from_address', config('mail.from.address'), 'mail'),
                'mail.from.name' => SystemSetting::get('mail_from_name', config('mail.from.name'), 'mail'),
            ]);
        } catch (\Exception $e) {
            // DB nicht erreichbar, .env-Fallback nutzen
        }
    }

    /**
     * Dienste bootstrappen.
     */
    public function boot(): void
    {
        // --- URL: Immer APP_URL verwenden (nicht Request-Host) ---
        // Wichtig fuer Einladungslinks, wenn Server ueber 127.0.0.1 aufgerufen wird
        // aber Links fuer externe Geraete (Handy) generiert werden muessen.
        \Illuminate\Support\Facades\URL::forceRootUrl(config('app.url'));

        // --- DSGVO: Auth-Event-Listener fuer Audit-Logging ---
        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Logout::class, LogSuccessfulLogout::class);
        Event::listen(Failed::class, LogFailedLogin::class);

        // --- System-SMTP aus DB laden (ueberschreibt .env) ---
        $this->configureSystemMailer();
    }
}
