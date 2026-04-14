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

        Log::info('selectTemplate', ['tenantId' => $tenantId, 'station' => $station?->name, 'category' => $category, 'slug' => $slug]);

        if ($station) {
            $station->setLabelTemplateSlug($category, $slug);
            Log::info('Template gespeichert', ['settings' => $station->fresh()->settings]);
            Notification::make()
                ->title('Vorlage ausgewaehlt')
                ->body("\"$slug\" ist jetzt aktiv fuer $category.")
                ->success()
                ->send();
        }
    }

    /**
     * Demo-Druck mit Beispieldaten.
     */
    public function demoPrint(string $slug): void
    {
        $template = LabelTemplate::active()->bySlug($slug)->first();

        if (!$template) {
            Notification::make()->title('Vorlage nicht gefunden')->danger()->send();
            return;
        }

        // Beispieldaten aus Platzhaltern generieren
        $data = [];
        $placeholders = is_string($template->placeholders) ? json_decode($template->placeholders, true) : $template->placeholders;
        if (is_array($placeholders)) {
            foreach ($placeholders as $p) {
                $data[$p['key']] = $p['example'] ?? $p['key'];
            }
        }
        // Datum immer aktuell
        $data['datum'] = now()->format('d.m.Y H:i');

        $labelXml = $template->render($data);

        // Ueber lokalen DYMO Service drucken
        try {
            $port = $this->findDymoPort();
            $printParams = '<LabelWriterPrintParams><Copies>1</Copies></LabelWriterPrintParams>';

            $ch = curl_init("https://localhost:{$port}/DYMO/DLS/Printing/PrintLabel2");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'printerName' => 'DYMO LabelWriter Wireless',
                    'labelXml' => $labelXml,
                    'printParamsXml' => $printParams,
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            ]);
            $result = curl_exec($ch);
            curl_close($ch);

            if ($result === 'true') {
                Notification::make()->title('Demo gedruckt')->body("Vorlage \"$slug\" wurde gedruckt.")->success()->send();
            } else {
                Notification::make()->title('Druckfehler')->body($result ?: 'Keine Antwort')->danger()->send();
            }
        } catch (\Exception $e) {
            Notification::make()->title('Druckfehler')->body($e->getMessage())->danger()->send();
        }
    }

    private function findDymoPort(): int
    {
        $ports = [41951, 41952, 41953, 41954, 41955];
        foreach ($ports as $port) {
            $ch = curl_init("https://localhost:{$port}/DYMO/DLS/Printing/StatusConnected");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 3,
            ]);
            $result = curl_exec($ch);
            curl_close($ch);
            if ($result === 'true') return $port;
        }
        throw new \Exception('DYMO Service nicht erreichbar (Ports 41951-41955)');
    }
}
