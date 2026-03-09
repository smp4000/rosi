<?php

use App\Http\Middleware\CheckSubscription;
use App\Http\Middleware\CheckTrialExpired;
use App\Http\Middleware\EnsureConsent;
use App\Http\Middleware\EnsureTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Middleware-Aliase fuer Route-Gruppen
        $middleware->alias([
            'tenant' => EnsureTenantContext::class,
            'trial' => CheckTrialExpired::class,
            'subscription' => CheckSubscription::class,
            'consent' => EnsureConsent::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
