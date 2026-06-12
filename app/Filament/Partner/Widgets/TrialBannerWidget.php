<?php

namespace App\Filament\Partner\Widgets;

use Filament\Widgets\Widget;

/**
 * Dashboard-Widget: Trial-Banner wenn der Mandant in der Testphase ist.
 */
class TrialBannerWidget extends Widget
{
    protected static ?int $sort = 0;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.partner.widgets.trial-banner';

    use \App\Filament\Concerns\HasWidgetCatalogPermission;

    /** Sichtbarkeit ueber die Rollen-Matrix */
    protected static string $accessPermission = 'partner.dashboard.view';

    public static function canView(): bool
    {
        // Nur waehrend der Testphase UND mit Dashboard-Recht
        return (auth()->user()->tenant?->isOnTrial() ?? false)
            && static::katalogWidgetPermission();
    }

    public function getDaysRemaining(): int
    {
        return (int) now()->diffInDays(auth()->user()->tenant->trial_ends_at, false);
    }

    public function getTrialEndDate(): string
    {
        return auth()->user()->tenant->trial_ends_at->format('d.m.Y');
    }
}
