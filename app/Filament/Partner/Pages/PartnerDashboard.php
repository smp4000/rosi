<?php

namespace App\Filament\Partner\Pages;

use App\Filament\Concerns\HasPageCatalogPermission;
use Filament\Pages\Dashboard;

/**
 * Partner-Dashboard mit Rechte-Pruefung (Rollen-Matrix).
 * Ohne partner.dashboard.view: kein Dashboard, keine Schnellaktionen,
 * keine Statistik-Widgets.
 */
class PartnerDashboard extends Dashboard
{
    use HasPageCatalogPermission;

    /** Permission fuer den Seitenzugriff (Rollen-Matrix) */
    protected static string $accessPermission = 'partner.dashboard.view';
}
