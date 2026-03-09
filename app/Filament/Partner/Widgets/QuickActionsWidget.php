<?php

namespace App\Filament\Partner\Widgets;

use Filament\Widgets\Widget;

/**
 * Dashboard-Widget: Schnellaktionen fuer haeufige Aufgaben.
 */
class QuickActionsWidget extends Widget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.partner.widgets.quick-actions';
}
