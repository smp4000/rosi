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

        // --- Rollen-Matrix: Standard-Buttons automatisch ausblenden ---
        // Filament blendet Create/Edit/Delete/View-Buttons NICHT selbst aus,
        // wenn can*() der Resource false liefert — das holen wir hier global
        // nach. Ohne Recht verschwindet der Button; der URL-Direktaufruf
        // bleibt durch die can*-Methoden weiterhin 403.
        $this->gateFilamentStandardActions();
    }

    /**
     * Sichtbarkeit der Filament-Standard-Actions an die Rechte-Pruefung
     * der jeweiligen Resource koppeln (Rollen-Matrix). Gilt fuer alle
     * Panels und alle Resources — ohne Aenderung an den einzelnen Seiten.
     */
    private function gateFilamentStandardActions(): void
    {
        // Resource der Seite ermitteln, auf der die Action gerendert wird
        $resourceOf = function ($action) {
            $livewire = $action->getLivewire();

            return (is_object($livewire) && method_exists($livewire, 'getResource'))
                ? $livewire::getResource()
                : null;
        };

        \Filament\Actions\CreateAction::configureUsing(function ($action) use ($resourceOf) {
            $action->visible(function () use ($action, $resourceOf) {
                $resource = $resourceOf($action);

                return $resource === null || $resource::canCreate();
            });
        });

        \Filament\Actions\EditAction::configureUsing(function ($action) use ($resourceOf) {
            $action->visible(function () use ($action, $resourceOf) {
                $resource = $resourceOf($action);

                return $resource === null || $resource::canEdit($action->getRecord());
            });
        });

        \Filament\Actions\ViewAction::configureUsing(function ($action) use ($resourceOf) {
            $action->visible(function () use ($action, $resourceOf) {
                $resource = $resourceOf($action);

                return $resource === null || $resource::canView($action->getRecord());
            });
        });

        \Filament\Actions\DeleteAction::configureUsing(function ($action) use ($resourceOf) {
            $action->visible(function () use ($action, $resourceOf) {
                $resource = $resourceOf($action);
                if ($resource === null) {
                    return true;
                }

                $record = $action->getRecord();

                return $record ? $resource::canDelete($record) : $resource::canDeleteAny();
            });
        });

        \Filament\Actions\DeleteBulkAction::configureUsing(function ($action) use ($resourceOf) {
            $action->visible(function () use ($action, $resourceOf) {
                $resource = $resourceOf($action);

                return $resource === null || $resource::canDeleteAny();
            });
        });

        \Filament\Actions\RestoreAction::configureUsing(function ($action) use ($resourceOf) {
            $action->visible(function () use ($action, $resourceOf) {
                $resource = $resourceOf($action);

                return $resource === null || $resource::canRestore($action->getRecord());
            });
        });

        \Filament\Actions\ForceDeleteAction::configureUsing(function ($action) use ($resourceOf) {
            $action->visible(function () use ($action, $resourceOf) {
                $resource = $resourceOf($action);

                return $resource === null || $resource::canForceDelete($action->getRecord());
            });
        });

        \Filament\Actions\ReplicateAction::configureUsing(function ($action) use ($resourceOf) {
            $action->visible(function () use ($action, $resourceOf) {
                $resource = $resourceOf($action);

                return $resource === null || $resource::canReplicate($action->getRecord());
            });
        });
    }
}
