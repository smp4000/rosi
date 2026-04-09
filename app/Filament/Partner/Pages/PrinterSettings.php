<?php

namespace App\Filament\Partner\Pages;

use Filament\Pages\Page;

/**
 * Drucker-Einstellungen: DYMO LabelWriter ueber JavaScript SDK.
 *
 * Die gesamte Drucker-Kommunikation laeuft im Browser des Nutzers,
 * da der DYMO Connect Service auf dem lokalen PC laeuft (localhost:41951).
 * Kein Server-Roundtrip noetig.
 */
class PrinterSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-printer';

    protected static ?string $navigationLabel = 'Drucker';

    protected static ?string $title = 'Drucker-Einstellungen';

    protected static ?string $slug = 'printer-settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Einstellungen';

    protected static ?int $navigationSort = 92;

    protected string $view = 'filament.partner.pages.printer-settings';
}
