<?php

namespace App\Filament\Partner\Pages;

use App\Models\GasStation;
use App\Models\LabelTemplate;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Session;

class LabelTemplateSelection extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Druckvorlagen';
    protected static ?string $title = 'Druckvorlagen auswaehlen';
    protected static ?string $slug = 'label-template-selection';
    protected static string|\UnitEnum|null $navigationGroup = 'Einstellungen';
    protected static ?int $navigationSort = 93;

    protected string $view = 'filament.partner.pages.label-template-selection';

    public function getViewData(): array
    {
        $tenantId = Session::get('tenant_id');
        $station = GasStation::find($tenantId);

        // Alle aktiven globalen Templates nach Kategorie gruppiert
        $categories = LabelTemplate::active()
            ->whereNull('tenant_id')
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        // Aktuelle Auswahl der Station
        $selections = $station?->settings['label_templates'] ?? [];

        // Kategorie-Labels aus MODELS
        $categoryLabels = [];
        foreach (LabelTemplate::MODELS as $key => $config) {
            $categoryLabels[$key] = $config['label'];
        }

        return [
            'categories' => $categories,
            'selections' => $selections,
            'categoryLabels' => $categoryLabels,
            'tenantId' => $tenantId,
        ];
    }

    /**
     * Template-Auswahl speichern (Livewire Action).
     */
    public function selectTemplate(string $category, string $slug): void
    {
        $tenantId = Session::get('tenant_id');
        $station = GasStation::find($tenantId);

        if ($station) {
            $station->setLabelTemplateSlug($category, $slug);
            $this->dispatch('notify', type: 'success', message: "Vorlage \"$slug\" fuer $category ausgewaehlt");
        }
    }
}
