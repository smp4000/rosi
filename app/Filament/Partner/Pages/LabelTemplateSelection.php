<?php

namespace App\Filament\Partner\Pages;

use App\Models\GasStation;
use App\Models\LabelTemplate;
use App\Models\PrintAgent;
use App\Services\PrintQueueService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class LabelTemplateSelection extends Page
{
    use \App\Filament\Concerns\HasPageCatalogPermission;

    /** Permission fuer den Seitenzugriff (Rollen-Matrix) */
    protected static string $accessPermission = 'partner.print.settings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Druckvorlagen';
    protected static ?string $title = 'Druckvorlagen auswaehlen';
    protected static ?string $slug = 'label-template-selection';
    protected static string|\UnitEnum|null $navigationGroup = 'Einstellungen';
    protected static ?int $navigationSort = 93;

    protected string $view = 'filament.partner.pages.label-template-selection';

    // Livewire-Property: aktuelle Auswahl (wird nach selectTemplate aktualisiert)
    public array $selections = [];

    // Livewire-Property: gewaehltes Druckziel (agentId|drucker) fuer den Demo-Druck
    public ?string $printTarget = null;

    public function mount(): void
    {
        $this->loadSelections();
        $this->printTarget = static::defaultDestinationKey();
    }

    private function loadSelections(): void
    {
        $tenantId = Session::get('tenant_id');
        $station = GasStation::find($tenantId) ?? GasStation::where('tenant_id', $tenantId)->first();
        $this->selections = $station?->settings['label_templates'] ?? [];
    }

    /** Aktuelle Station des angemeldeten Partners ermitteln. */
    private function resolveStation(): ?GasStation
    {
        $tenantId = Session::get('tenant_id') ?? auth()->user()?->tenant_id;
        return GasStation::find($tenantId) ?? GasStation::where('tenant_id', $tenantId)->first();
    }

    /** Druckziele der Station als Optionen (agentId|drucker => Label). */
    protected static function destinationOptions(): array
    {
        $tenantId = Session::get('tenant_id') ?? auth()->user()?->tenant_id;
        $station = GasStation::find($tenantId) ?? GasStation::where('tenant_id', $tenantId)->first();
        if (! $station) {
            return [];
        }

        $agents = PrintAgent::where('station_id', $station->id)->where('is_active', true)->get();
        $multi = $agents->count() > 1;

        $opts = [];
        foreach ($agents as $agent) {
            foreach (($agent->printers ?? []) as $printer) {
                $opts[$agent->id . '|' . $printer] = $multi ? ($agent->name . ' · ' . $printer) : $printer;
            }
        }

        return $opts;
    }

    /** Vorauswahl: Standard-Drucker der Station (printer_map['*']) oder erster. */
    protected static function defaultDestinationKey(): ?string
    {
        $options = static::destinationOptions();
        if (empty($options)) {
            return null;
        }

        $tenantId = Session::get('tenant_id') ?? auth()->user()?->tenant_id;
        $station = GasStation::find($tenantId) ?? GasStation::where('tenant_id', $tenantId)->first();
        $defaultPrinter = $station?->printer_map['*'] ?? null;

        if ($defaultPrinter) {
            foreach (array_keys($options) as $key) {
                if (str_ends_with($key, '|' . $defaultPrinter)) {
                    return $key;
                }
            }
        }

        return array_key_first($options);
    }

    /**
     * Demo-Etikett in die Druck-Queue legen -> der Stations-Agent druckt es
     * lokal am DYMO. (Frueher lief das per fetch() direkt im Browser.)
     */
    public function demoPrint(string $slug): void
    {
        $template = LabelTemplate::active()->bySlug($slug)
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', Session::get('tenant_id')))
            ->first();

        if (! $template) {
            Notification::make()->title('Vorlage nicht gefunden')->danger()->send();
            return;
        }

        $station = $this->resolveStation();
        if (! $station) {
            Notification::make()->title('Tankstelle nicht gefunden')->danger()->send();
            return;
        }

        if (empty(static::destinationOptions())) {
            Notification::make()
                ->title('Kein Drucker verbunden')
                ->body('Auf diesem Stations-PC ist kein Druck-Agent online. Bitte ROSI Print starten.')
                ->warning()
                ->send();
            return;
        }

        // Beispieldaten aus den Platzhaltern bauen
        $ph = is_string($template->placeholders)
            ? json_decode($template->placeholders, true)
            : ($template->placeholders ?? []);

        $demoData = [];
        foreach ((array) $ph as $p) {
            if (! empty($p['key'])) {
                $demoData[$p['key']] = $p['example'] ?? $p['key'];
            }
        }
        // Datum/Zeit auf jetzt setzen - Format am Platzhalter-Beispiel erkennen:
        //  - "06/2026" (enthaelt "/") -> nur Monat/Jahr
        //  - getrennte zeit-Spalte     -> Datum + Uhrzeit getrennt
        //  - sonst                     -> komplettes Datum mit Uhrzeit
        if (array_key_exists('datum', $demoData) && str_contains((string) $demoData['datum'], '/')) {
            $demoData['datum'] = now()->format('m/Y');
        } elseif (array_key_exists('zeit', $demoData)) {
            $demoData['datum'] = now()->format('d.m.Y');
            $demoData['zeit'] = now()->format('H:i');
        } elseif (array_key_exists('datum', $demoData)) {
            $demoData['datum'] = now()->format('d.m.Y H:i');
        }

        try {
            $xml = $template->render($demoData);
        } catch (\Throwable $e) {
            Notification::make()->title('Etikett konnte nicht erzeugt werden')->body($e->getMessage())->danger()->send();
            return;
        }

        // Druckziel aufloesen (agentId|drucker)
        $agentId = null;
        $printer = null;
        if ($this->printTarget && str_contains($this->printTarget, '|')) {
            [$agentId, $printer] = explode('|', $this->printTarget, 2);
        }

        try {
            app(PrintQueueService::class)->enqueue($station, 'demo_label', [
                ['number' => 'DEMO', 'xml' => $xml],
            ], [
                'reference' => $slug,
                'reference_type' => 'demo',
                'created_by' => auth()->user()->name,
                'user_id' => auth()->id(),
                'target_agent_id' => $agentId,
                'printer_name' => $printer,
            ]);
        } catch (\Throwable $e) {
            Log::error('Demo-Druck Queue fehlgeschlagen', ['slug' => $slug, 'error' => $e->getMessage()]);
            Notification::make()->title('Demo-Druck fehlgeschlagen')->body($e->getMessage())->danger()->send();
            return;
        }

        Notification::make()
            ->title('Demo-Etikett an den Drucker gesendet')
            ->body($printer ? ('Drucker: ' . $printer) : 'Standard-Drucker der Station')
            ->success()
            ->send();
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
            'destinations' => static::destinationOptions(),
        ];
    }

    /**
     * Template-Auswahl speichern (Livewire Action).
     */
    public function selectTemplate(string $category, string $slug): void
    {
        $tenantId = Session::get('tenant_id');
        $station = GasStation::find($tenantId) ?? GasStation::where('tenant_id', $tenantId)->first();

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
