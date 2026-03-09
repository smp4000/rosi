<?php

namespace App\Providers;

use App\Listeners\LogFailedLogin;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
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
     * Dienste bootstrappen.
     */
    public function boot(): void
    {
        // --- DSGVO: Auth-Event-Listener fuer Audit-Logging ---
        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Logout::class, LogSuccessfulLogout::class);
        Event::listen(Failed::class, LogFailedLogin::class);
    }
}
