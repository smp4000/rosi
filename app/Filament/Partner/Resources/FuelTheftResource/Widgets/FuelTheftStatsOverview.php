<?php

namespace App\Filament\Partner\Resources\FuelTheftResource\Widgets;

use App\Models\FuelTheft;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Auswertung der Tankbetruege: Offen, Bezahlt, Gesamt.
 */
class FuelTheftStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $tenantId = auth()->user()?->tenant_id;
        $query = FuelTheft::where('tenant_id', $tenantId);

        $total = (clone $query)->count();
        $totalAmount = (clone $query)->sum('amount');
        $totalPaid = (clone $query)->sum('payment_amount');
        $totalOpen = $totalAmount - $totalPaid;

        $countOpen = (clone $query)->where('payment_status', 'open')->count();
        $countPartial = (clone $query)->where('payment_status', 'partial')->count();
        $countPaid = (clone $query)->where('payment_status', 'paid')->count();
        $countWrittenOff = (clone $query)->where('payment_status', 'written_off')->count();

        $countSubmitted = (clone $query)->where('status', 'submitted')->count();
        $countInProgress = (clone $query)->where('status', 'in_progress')->count();
        $countResolved = (clone $query)->where('status', 'resolved')->count();

        $countPoliceReport = (clone $query)->whereNotNull('police_report_at')->count();
        $countCompany = (clone $query)->whereNotNull('submitted_to_company_at')->count();

        return [
            Stat::make('Vorfaelle gesamt', $total)
                ->description("{$countSubmitted} gesendet, {$countInProgress} in Bearbeitung, {$countResolved} erledigt")
                ->icon('heroicon-o-exclamation-triangle')
                ->color('gray'),

            Stat::make('Offener Betrag', number_format($totalOpen, 2, ',', '.') . ' EUR')
                ->description("{$countOpen} offen, {$countPartial} Teilzahlung")
                ->icon('heroicon-o-banknotes')
                ->color($totalOpen > 0 ? 'danger' : 'success'),

            Stat::make('Erhalten', number_format($totalPaid, 2, ',', '.') . ' EUR')
                ->description("{$countPaid} bezahlt, {$countWrittenOff} abgeschrieben")
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Eingereicht', "{$countPoliceReport} / {$countCompany}")
                ->description("{$countPoliceReport} Anzeigen, {$countCompany} bei Gesellschaft")
                ->icon('heroicon-o-building-office')
                ->color('info'),
        ];
    }
}
