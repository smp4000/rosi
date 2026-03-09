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

    public static function canView(): bool
    {
        return auth()->user()->tenant?->isOnTrial() ?? false;
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
