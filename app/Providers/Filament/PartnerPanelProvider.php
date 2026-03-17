<?php

namespace App\Providers\Filament;

use App\Http\Middleware\CheckTrialExpired;
use App\Http\Middleware\EnsureTenantContext;
use App\Http\Middleware\SetPartnerPermissionsTeam;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PartnerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('partner')
            ->path('dashboard')
            ->login()
            ->brandName('ROSI')
            ->colors([
                'primary' => Color::Indigo,
                'danger' => Color::Rose,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Orange,
            ])
            ->navigationGroups([
                NavigationGroup::make('Tankstellen')
                    ->icon('heroicon-o-map-pin'),
                NavigationGroup::make('Personal')
                    ->icon('heroicon-o-users'),
                NavigationGroup::make('Dokumente')
                    ->icon('heroicon-o-document-text'),
                NavigationGroup::make('Kommunikation')
                    ->icon('heroicon-o-chat-bubble-left-right'),
            ])
            ->font('Inter')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->discoverResources(in: app_path('Filament/Partner/Resources'), for: 'App\\Filament\\Partner\\Resources')
            ->discoverPages(in: app_path('Filament/Partner/Pages'), for: 'App\\Filament\\Partner\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Partner/Widgets'), for: 'App\\Filament\\Partner\\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureTenantContext::class,
                CheckTrialExpired::class,
                SetPartnerPermissionsTeam::class,
            ]);
    }
}
