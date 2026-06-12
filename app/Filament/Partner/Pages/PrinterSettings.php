<?php

namespace App\Filament\Partner\Pages;

use Filament\Pages\Page;

/**
 * Drucker-Einstellungen: DYMO LabelWriter ueber JavaScript SDK.
 *
 * Die gesamte Drucker-Kommunikation laeuft im Browser des Nutzers,
 * da der DYMO Connect Service auf dem lokalen PC laeuft (localhost:41951).
 * Nur auf dem lokalen Testserver sichtbar (nicht auf Production/All-Inkl).
 */
class PrinterSettings extends Page
{
    use \App\Filament\Concerns\HasPageCatalogPermission;

    /** Permission fuer den Seitenzugriff (Rollen-Matrix) */
    protected static string $accessPermission = 'partner.print.settings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-printer';

    protected static ?string $navigationLabel = 'Drucker';

    protected static ?string $title = 'Drucker-Einstellungen';

    protected static ?string $slug = 'printer-settings';

    protected static string|\UnitEnum|null $navigationGroup = 'Einstellungen';

    protected static ?int $navigationSort = 92;

    protected string $view = 'filament.partner.pages.printer-settings';
}
