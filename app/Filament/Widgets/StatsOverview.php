<?php

namespace App\Filament\Widgets;

use App\Models\GasStation;
use App\Models\Tenant;
use App\Models\User;
use App\Scopes\TenantScope;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard-Widget: Zentrale Plattform-Statistiken.
 */
class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('is_active', true)->count();
        $totalStations = GasStation::withoutGlobalScope(TenantScope::class)->count();
        $activeStations = GasStation::withoutGlobalScope(TenantScope::class)->where('is_active', true)->count();
        $activeUsers = User::where('is_active', true)->count();
        $partnerCount = User::where('type', 'partner')->count();
        $trialCount = Tenant::where('subscription_status', 'trial')->count();
        $paidCount = Tenant::where('subscription_status', 'active')->count();

        return [
            Stat::make(__('admin.dashboard.stats.active_tenants'), $activeTenants)
                ->description(__('admin.dashboard.stats.total_tenants') . ': ' . $totalTenants)
                ->descriptionIcon('heroicon-o-building-office-2')
                ->color('success'),

            Stat::make(__('admin.dashboard.stats.total_gas_stations'), $totalStations)
                ->description(__('admin.dashboard.stats.active_gas_stations') . ': ' . $activeStations)
                ->descriptionIcon('heroicon-o-map-pin')
                ->color('primary'),

            Stat::make(__('admin.dashboard.stats.active_users'), $activeUsers)
                ->description(__('admin.dashboard.stats.partners') . ': ' . $partnerCount)
                ->descriptionIcon('heroicon-o-users')
                ->color('info'),

            Stat::make(__('admin.dashboard.stats.trial_tenants'), $trialCount)
                ->description(__('admin.dashboard.stats.paid_tenants') . ': ' . $paidCount)
                ->descriptionIcon('heroicon-o-credit-card')
                ->color('warning'),
        ];
    }
}
