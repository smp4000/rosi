<?php

namespace App\Filament\Partner\Pages;

use App\Models\GasStation;
use App\Models\LabelTemplate;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
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

    // Livewire-Property: aktuelle Auswahl (wird nach selectTemplate aktualisiert)
    public array $selections = [];

    public function mount(): void
    {
        $this->loadSelections();
    }

    private function loadSelections(): void
    {
        $tenantId = Session::get('tenant_id');
        $station = GasStation::find($tenantId);
        $this->selections = $station?->settings['label_templates'] ?? [];
    }

    public function getViewData(): array
    {
        // Alle aktiven globalen Templates nach Kategorie gruppiert
        $categories = LabelTemplate::active()
            ->whereNull('tenant_id')
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->groupBy('category');

        // Kategorie-Labels aus MODELS
        $categoryLabels = [];
        foreach (LabelTemplate::MODELS as $key => $config) {
            $categoryLabels[$key] = $config['label'];
        }

        return [
            'categories' => $categories,
            'categoryLabels' => $categoryLabels,
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

            // Selections aktualisieren damit die View sich updatet
            $this->loadSelections();

            Notification::make()
                ->title('Vorlage ausgewaehlt')
                ->body("\"$slug\" ist jetzt aktiv fuer $category.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Fehler')
                ->body('Station nicht gefunden (tenant_id: ' . ($tenantId ?? 'null') . ')')
                ->danger()
                ->send();
        }
    }
}
