<?php

namespace App\Filament\Partner\Widgets;

use App\Models\FuelTheft;
use App\Models\GasStation;
use App\Models\Mhd;
use App\Notifications\FuelTheftNotification;
use App\Notifications\MhdAlertNotification;
use Carbon\Carbon;
use Filament\Widgets\Widget;

/**
 * Dashboard-Widget: Warnungen und kritische Hinweise.
 * Sortierung: Kritisch (rot) vor Warnung (orange), neueste zuerst.
 * Kritische Meldungen werden fett dargestellt.
 */
class DashboardAlertsWidget extends Widget
{
    protected static ?int $sort = -1; // Ganz oben

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.partner.widgets.dashboard-alerts';

    /**
     * Alle aktiven Warnungen sammeln, sortiert nach Prioritaet.
     *
     * @return array<array{level: string, title: string, message: string, url: string|null}>
     */
    public function getAlerts(): array
    {
        $alerts = [];
        $tenantId = session('tenant_id');

        if (! $tenantId) {
            return [];
        }

        $stationIds = GasStation::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->pluck('id', 'name')
            ->toArray();

        if (empty($stationIds)) {
            return [];
        }

        // --- Tankbetrug-Alerts ---
        $newFuelThefts = FuelTheft::where('tenant_id', $tenantId)
            ->where('status', 'submitted')
            ->count();

        if ($newFuelThefts > 0) {
            $alerts[] = [
                'level' => 'danger',
                'title' => $newFuelThefts . ' ' . ($newFuelThefts === 1 ? 'neuer Tankbetrug' : 'neue Tankbetruege') . ' gemeldet',
                'message' => 'Bitte pruefen und bearbeiten.',
                'url' => route('filament.partner.resources.fuel-thefts.index'),
            ];
        }

        $inProgressFuelThefts = FuelTheft::where('tenant_id', $tenantId)
            ->where('status', 'in_progress')
            ->count();

        if ($inProgressFuelThefts > 0) {
            $alerts[] = [
                'level' => 'warning',
                'title' => $inProgressFuelThefts . ' ' . ($inProgressFuelThefts === 1 ? 'Tankbetrug' : 'Tankbetruege') . ' in Bearbeitung',
                'message' => 'Offene Faelle weiter bearbeiten.',
                'url' => route('filament.partner.resources.fuel-thefts.index'),
            ];
        }

        // --- MHD-Alerts ---
        $today = Carbon::today()->toDateString();
        $warningDate = Carbon::today()->addDays(7)->toDateString();

        // Pro Station: abgelaufene und bald ablaufende Artikel zaehlen
        foreach ($stationIds as $name => $stationId) {
            $expiredCount = Mhd::where('station_id', $stationId)
                ->notDisposed()
                ->where('mhd_date', '<', $today)
                ->count();

            $warningCount = Mhd::where('station_id', $stationId)
                ->notDisposed()
                ->where('mhd_date', '>=', $today)
                ->where('mhd_date', '<=', $warningDate)
                ->count();

            if ($expiredCount > 0) {
                $alerts[] = [
                    'level' => 'danger',
                    'title' => $name . ': ' . $expiredCount . ' Artikel mit abgelaufener Restlaufzeit',
                    'message' => 'Bitte pruefen und ggf. abschreiben.',
                    'url' => route('filament.partner.resources.mhds.index'),
                ];
            }

            if ($warningCount > 0) {
                $alerts[] = [
                    'level' => 'warning',
                    'title' => $name . ': ' . $warningCount . ' Artikel laufen in den naechsten 7 Tagen ab',
                    'message' => 'Bitte rechtzeitig kontrollieren.',
                    'url' => route('filament.partner.resources.mhds.index'),
                ];
            }
        }

        // Sortierung: danger (0) vor warning (1)
        usort($alerts, fn ($a, $b) => ($a['level'] === 'danger' ? 0 : 1) <=> ($b['level'] === 'danger' ? 0 : 1));

        return $alerts;
    }

    /**
     * Beim Laden des Widgets: Bell-Notifications aktualisieren.
     * Alte MHD-Notifications loeschen, aktuelle neu senden.
     */
    public function mount(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        // Alte Notifications dieses Users loeschen
        $user->notifications()
            ->whereIn('type', [
                MhdAlertNotification::class,
                FuelTheftNotification::class,
            ])
            ->delete();

        // Aktuelle Alerts als Bell-Notifications senden
        $alerts = $this->getAlerts();
        foreach ($alerts as $alert) {
            // Tankbetrug-Alerts als FuelTheftNotification, Rest als MHD
            $isFuelTheft = str_contains($alert['title'], 'Tankbetrug') || str_contains($alert['title'], 'Tankbetruege');
            if ($isFuelTheft) {
                $user->notify(new FuelTheftNotification(
                    title: $alert['title'],
                    body: $alert['message'],
                    level: $alert['level'],
                ));
            } else {
                $user->notify(new MhdAlertNotification(
                    title: $alert['title'],
                    body: $alert['message'],
                    level: $alert['level'],
                ));
            }
        }
    }

    public static function canView(): bool
    {
        return true;
    }
}
