<?php

namespace App\Filament\Partner\Widgets;

use App\Models\AppVersion;
use Filament\Widgets\Widget;

/**
 * Dashboard-Widget: Link zur Versionshistorie (Modal).
 */
class VersionHistoryWidget extends Widget
{
    use \App\Filament\Concerns\HasWidgetCatalogPermission;

    /** Sichtbarkeit ueber die Rollen-Matrix */
    protected static string $accessPermission = 'partner.dashboard.view';

    protected static ?int $sort = 99;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.partner.widgets.version-history';

    public function getVersions(): array
    {
        return AppVersion::published()
            ->where('platform', 'web')
            ->latestFirst()
            ->get()
            ->map(fn (AppVersion $v) => [
                'version' => $v->version,
                'date' => $v->release_date->format('d.m.Y'),
                'changes' => $v->changes ?? [],
            ])
            ->toArray();
    }
}
