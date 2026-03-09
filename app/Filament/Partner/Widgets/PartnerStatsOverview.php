<?php

namespace App\Filament\Partner\Widgets;

use App\Models\Customer;
use App\Models\Document;
use App\Models\GasStation;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard-Widget: Uebersicht der wichtigsten Kennzahlen fuer den Partner.
 */
class PartnerStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $tenantId = session('tenant_id');

        $totalStations = GasStation::count();
        $activeStations = GasStation::where('is_active', true)->count();

        $employeeCount = User::where('tenant_id', $tenantId)
            ->where('type', 'employee')
            ->where('is_active', true)
            ->count();

        return [
            Stat::make(__('partner.dashboard.stats.gas_stations'), $totalStations)
                ->description(__('partner.dashboard.stats.active') . ': ' . $activeStations)
                ->descriptionIcon('heroicon-o-map-pin')
                ->color('primary'),

            Stat::make(__('partner.dashboard.stats.employees'), $employeeCount)
                ->descriptionIcon('heroicon-o-users')
                ->color('success'),

            Stat::make(__('partner.dashboard.stats.customers'), Customer::count())
                ->descriptionIcon('heroicon-o-user-group')
                ->color('info'),

            Stat::make(__('partner.dashboard.stats.documents'), Document::count())
                ->descriptionIcon('heroicon-o-document-text')
                ->color('warning'),
        ];
    }
}
