<?php

namespace App\Filament\Partner\Pages;

use App\Models\ArticleImport;
use App\Models\GasStation;
use App\Services\ArticleCsvImportService;
use App\Services\ArticleDeleteCsvImportService;
use App\Services\ArticleEanCsvImportService;
use App\Services\ArticleLockCsvImportService;
use App\Services\CorporateCustomerCsvImportService;
use App\Services\DeliveryNoteCsvImportService;
use App\Services\FuelCardCsvImportService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Universelle Import-Seite im Partner-Panel.
 * Multi-Upload: Mehrere CSV-Dateien gleichzeitig hochladen.
 * Automatische Reihenfolge: Artikelstammdaten zuerst, dann EAN-Daten.
 */
class ArticleImportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Daten-Import';

    protected static ?string $title = 'Daten-Import';

    protected static ?string $slug = 'data-import';

    protected static string|\UnitEnum|null $navigationGroup = 'Tankstellen';

    protected static ?int $navigationSort = 16;

    protected string $view = 'filament.partner.pages.article-import';

    public array $data = [];
    public array $analyzed_files = [];

    /**
     * Dateitypen mit Erkennungsmuster und Prioritaet.
     * Niedrigere priority = wird zuerst verarbeitet.
     */
    private static function getFilePatterns(): array
    {
        return [
            'articles' => [
                'label' => 'Artikelstammdaten',
                'pattern' => 'Nr.,,Bezeichnung,Einheit,,akt.EK',
                'icon' => '📦',
                'priority' => 1,
            ],
            'ean_data' => [
                'label' => 'EAN-Daten (Mengenverordnung)',
                'pattern' => 'Mengenverordnung',
                'icon' => '🏷️',
                'priority' => 2,
            ],
            'lock_list' => [
                'label' => 'Sperrliste',
                'pattern' => ['mit Kenner', 'Sperren'],
                'icon' => '🔒',
                'priority' => 3,
            ],
            'delete_list' => [
                'label' => 'Löschliste',
                'pattern' => ['mit Kenner', 'schen'],
                'icon' => '🗑️',
                'priority' => 4,
            ],
            'corporate_customers' => [
                'label' => 'Firmenliste',
                'pattern' => 'Firmenliste',
                'icon' => '🏢',
                'priority' => 5,
            ],
            'fuel_cards' => [
                'label' => 'Teilnehmerliste (Tankkarten)',
                'pattern' => 'Teilnehmerliste',
                'icon' => '💳',
                'priority' => 6,
            ],
            'delivery_note' => [
                'label' => 'Lieferschein (MHD-Import)',
                'pattern' => ['"Pos."', '"TMS Nr."', '"min MHD"'],
                'icon' => '📋',
                'priority' => 7,
            ],
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('CSV-Dateien hochladen')
                ->description('Laden Sie eine oder mehrere CSV-Dateien hoch. Der Dateityp wird automatisch erkannt.')
                ->schema([
                    FileUpload::make('csv_files')
                        ->label('CSV-Dateien')
                        ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain', '.csv'])
                        ->disk('local')
                        ->directory('temp/data-imports')
                        ->maxSize(10240)
                        ->multiple()
                        ->required()
                        ->helperText('CSV-Exports aus dem Kassensystem (max. 10 MB pro Datei). Mehrere Dateien gleichzeitig moeglich.')
                        ->live()
                        ->afterStateUpdated(function ($state) {
                            $this->analyzeUploadedFiles($state);
                        }),
                ])
                ->columns(1),

            // Analyse-Ergebnis
            Section::make('Erkannte Dateien')
                ->schema([
                    Placeholder::make('analysis_result')
                        ->label('')
                        ->content(fn () => $this->getAnalysisHtml()),
                ])
                ->visible(fn () => ! empty($this->analyzed_files))
                ->columns(1),

            // Import-Historie
            Section::make('Letzte Imports')
                ->description('Übersicht der letzten 10 Imports')
                ->schema([
                    Placeholder::make('import_history')
                        ->label('')
                        ->content(fn () => $this->getHistoryHtml()),
                ])
                ->collapsible(),
        ]);
    }

    /**
     * Hochgeladene Dateien analysieren und Ergebnis anzeigen.
     */
    public function analyzeUploadedFiles(mixed $state): void
    {
        $this->analyzed_files = [];

        if (empty($state)) {
            return;
        }

        $files = is_array($state) ? $state : [$state];
        $tenantId = auth()->user()?->tenant_id;

        foreach ($files as $fileRef) {
            $fullPath = null;
            $originalFilename = null;

            if ($fileRef instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                $fullPath = $fileRef->getRealPath();
                $originalFilename = $fileRef->getClientOriginalName();
            } else {
                $fullPath = $this->resolveFilePath($fileRef);
                if (! $fullPath) {
                    // Fallback: Neueste CSV aus livewire-tmp
                    $recentFiles = $this->getRecentCsvFiles();
                    if (! empty($recentFiles)) {
                        // Nimm eine die noch nicht zugeordnet ist
                        foreach ($recentFiles as $rf) {
                            $alreadyUsed = false;
                            foreach ($this->analyzed_files as $af) {
                                if (($af['path'] ?? '') === $rf) {
                                    $alreadyUsed = true;
                                    break;
                                }
                            }
                            if (! $alreadyUsed) {
                                $fullPath = $rf;
                                break;
                            }
                        }
                    }
                }
            }

            if (! $fullPath || ! file_exists($fullPath)) {
                continue;
            }

            $info = $this->analyzeFile($fullPath, $originalFilename);

            $stationName = '-';
            if ($info['station_number']) {
                $normalizedNr = ltrim($info['station_number'], '0');
                $station = GasStation::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($normalizedNr) {
                        $q->where('station_number', 'LIKE', '%' . $normalizedNr . '%')
                          ->orWhere('station_number_shop', 'LIKE', '%' . $normalizedNr . '%')
                          ->orWhere('station_number_fuel', 'LIKE', '%' . $normalizedNr . '%');
                    })
                    ->first();
                $stationName = $station ? $station->name : '⚠️ Nicht gefunden';
            }

            $this->analyzed_files[] = [
                'path' => $fullPath,
                'filename' => $originalFilename ?: basename($fullPath),
                'original_filename' => $originalFilename,
                'type_label' => $info['type_label'] ?: '❓ Unbekannt',
                'station_number' => $info['station_number'] ?: '-',
                'station_name' => $stationName,
                'priority' => $info['priority'],
                'error' => $info['error'],
                'status' => 'pending',
                'result' => '',
            ];
        }

        // Nach Prioritaet sortieren
        usort($this->analyzed_files, fn ($a, $b) => $a['priority'] <=> $b['priority']);
    }

    /**
     * Analyse-Ergebnis als HTML.
     */
    private function getAnalysisHtml(): HtmlString
    {
        if (empty($this->analyzed_files)) {
            return new HtmlString('');
        }

        $allOk = true;
        foreach ($this->analyzed_files as $f) {
            if ($f['error'] || str_starts_with($f['station_name'], '⚠️')) {
                $allOk = false;
                break;
            }
        }

        $borderColor = $allOk ? '#bbf7d0' : '#fde68a';
        $bgColor = $allOk ? '#f0fdf4' : '#fffbeb';

        $html = "<div style='padding:12px; background:{$bgColor}; border:1px solid {$borderColor}; border-radius:8px;'>";
        $html .= '<table style="width:100%; font-size:0.9rem; border-collapse:collapse;">';
        $html .= '<thead><tr style="border-bottom:1px solid #d1d5db;">';
        $html .= '<th style="padding:6px 8px; text-align:left; width:30px;"></th>';
        $html .= '<th style="padding:6px 8px; text-align:left;">Dateityp</th>';
        $html .= '<th style="padding:6px 8px; text-align:left;">Stationsnummer</th>';
        $html .= '<th style="padding:6px 8px; text-align:left;">Tankstelle</th>';
        $html .= '<th style="padding:6px 8px; text-align:left;">Ergebnis</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($this->analyzed_files as $i => $f) {
            $statusIcon = match ($f['status'] ?? 'pending') {
                'done' => '✅',
                'error' => '❌',
                'pending' => '<span style="color:#9ca3af;">⏳</span>',
                default => '⏳',
            };
            $resultText = $f['result'] ?? '';

            $html .= '<tr style="border-bottom:1px solid #f3f4f6;">';
            $html .= "<td style='padding:6px 8px;'>{$statusIcon}</td>";
            $html .= "<td style='padding:6px 8px;'>{$f['type_label']}</td>";
            $html .= "<td style='padding:6px 8px;'>{$f['station_number']}</td>";
            $html .= "<td style='padding:6px 8px;'>{$f['station_name']}</td>";
            $html .= "<td style='padding:6px 8px; font-size:0.8rem; color:#6b7280;'>{$resultText}</td>";
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        if (count($this->analyzed_files) > 1) {
            $hasUnprocessed = collect($this->analyzed_files)->contains(fn ($f) => ($f['status'] ?? 'pending') === 'pending');
            if ($hasUnprocessed) {
                $html .= '<div style="margin-top:8px; font-size:0.8rem; color:#6b7280;">Dateien werden in der angezeigten Reihenfolge verarbeitet.</div>';
            }
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    /**
     * Import starten - analyzed_files nacheinander verarbeiten.
     */
    public function importCsv(): void
    {
        // Falls noch nicht analysiert, jetzt nachholen
        if (empty($this->analyzed_files)) {
            $csvFiles = $this->data['csv_files'] ?? [];
            if (empty($csvFiles)) {
                Notification::make()->title('Keine Dateien ausgewählt')->danger()->send();
                return;
            }
            $this->analyzeUploadedFiles($csvFiles);
        }

        if (empty($this->analyzed_files)) {
            Notification::make()->title('Keine Dateien erkannt')->danger()->send();
            return;
        }

        // Nacheinander verarbeiten (bereits nach Prioritaet sortiert)
        $results = [];
        $hasErrors = false;

        foreach ($this->analyzed_files as $i => &$file) {
            if (! empty($file['error'])) {
                $file['status'] = 'error';
                $file['result'] = $file['error'];
                $hasErrors = true;
                continue;
            }

            $fullPath = $file['path'];
            if (! file_exists($fullPath)) {
                $file['status'] = 'error';
                $file['result'] = 'Datei nicht gefunden';
                $hasErrors = true;
                continue;
            }

            // Typ nochmal bestimmen
            $info = $this->analyzeFile($fullPath);
            $type = $info['type'];

            try {
                $import = match ($type) {
                    'articles' => (new ArticleCsvImportService())->import($fullPath, $file['filename'], auth()->id()),
                    'ean_data' => (new ArticleEanCsvImportService())->import($fullPath, $file['filename'], auth()->id()),
                    'delete_list' => (new ArticleDeleteCsvImportService())->import($fullPath, $file['filename'], auth()->id()),
                    'lock_list' => (new ArticleLockCsvImportService())->import($fullPath, $file['filename'], auth()->id()),
                    'corporate_customers' => (new CorporateCustomerCsvImportService())->import($fullPath, $file['filename'], auth()->id()),
                    'fuel_cards' => (new FuelCardCsvImportService())->import($fullPath, $file['filename'], auth()->id()),
                    'delivery_note' => (new DeliveryNoteCsvImportService())->import($fullPath, $file['original_filename'] ?? $file['filename'], auth()->id()),
                    default => throw new \Exception("Typ '{$type}' nicht unterstützt."),
                };

                $file['status'] = 'done';
                $file['result'] = $import->summary;
                $results[] = "{$file['type_label']}: {$import->summary}";
            } catch (\Exception $e) {
                $file['status'] = 'error';
                $file['result'] = $e->getMessage();
                $results[] = "❌ {$file['type_label']}: {$e->getMessage()}";
                $hasErrors = true;
            }

            // Datei loeschen
            $this->cleanupFile($fullPath);
        }
        unset($file);

        // Ergebnis anzeigen
        $body = implode("\n", $results);

        if ($hasErrors) {
            Notification::make()->title('Import teilweise abgeschlossen')->body($body)->warning()->send();
        } else {
            Notification::make()->title('Import erfolgreich!')->body($body)->success()->send();
        }

        // Formular zuruecksetzen (analyzed_files behalten fuer Status-Anzeige)
        $this->data['csv_files'] = [];
    }

    /**
     * Einzelne Datei analysieren - Typ und Metadaten erkennen.
     */
    private function analyzeFile(string $fullPath, ?string $originalFilename = null): array
    {
        $result = [
            'type' => null,
            'type_label' => '',
            'priority' => 99,
            'station_number' => '',
            'error' => null,
        ];

        try {
            $content = file_get_contents($fullPath);
            if (! mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
            }

            $lines = explode("\n", $content);
            $headerText = implode("\n", array_slice($lines, 0, 50));

            // Dateityp erkennen
            // Sperr-/Loeschliste zuerst pruefen (hoehere Spezifitaet als Artikelstammdaten/EAN)
            $filePatterns = self::getFilePatterns();
            $orderedTypes = ['lock_list', 'delete_list', 'delivery_note', 'corporate_customers', 'fuel_cards', 'articles', 'ean_data'];
            foreach ($orderedTypes as $type) {
                if (! isset($filePatterns[$type])) {
                    continue;
                }
                $config = $filePatterns[$type];
                $needles = is_array($config['pattern']) ? $config['pattern'] : [$config['pattern']];
                $matched = true;
                foreach ($needles as $needle) {
                    if (! str_contains($headerText, $needle)) {
                        $matched = false;
                        break;
                    }
                }
                if ($matched) {
                    $result['type'] = $type;
                    $result['type_label'] = $config['icon'] . ' ' . $config['label'];
                    $result['priority'] = $config['priority'];
                    break;
                }
            }

            if (! $result['type']) {
                $result['error'] = 'Unbekannter Dateityp';
                return $result;
            }

            // Stationsnummer extrahieren
            foreach ($lines as $line) {
                if (preg_match('/Stationsnummer:\s*(\d+)/', $line, $m)) {
                    $result['station_number'] = $m[1];
                    break;
                }
            }

            // Lieferschein: Stationsnummer aus Dateinamen (LS_Tankstellennummer.csv)
            if (empty($result['station_number']) && $result['type'] === 'delivery_note') {
                $nameForParsing = $originalFilename ?: basename($fullPath);
                $basename = pathinfo($nameForParsing, PATHINFO_FILENAME);
                $parts = explode('_', $basename);
                if (count($parts) >= 2) {
                    $result['station_number'] = end($parts);
                }
            }
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Dateipfad aufloesen.
     */
    private function resolveFilePath(mixed $fileRef): ?string
    {
        $filePath = is_array($fileRef) ? reset($fileRef) : $fileRef;

        // Direkte Pfade pruefen
        $candidates = [
            storage_path('app/private/' . $filePath),
            storage_path('app/' . $filePath),
            storage_path('app/private/livewire-tmp/' . $filePath),
            storage_path('app/livewire-tmp/' . $filePath),
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Alle aktuellen CSV-Dateien aus livewire-tmp holen.
     * Fallback wenn resolveFilePath nicht funktioniert.
     */
    private function getRecentCsvFiles(): array
    {
        $tmpDir = storage_path('app/private/livewire-tmp');
        if (! is_dir($tmpDir)) {
            return [];
        }

        $files = [];
        foreach (glob($tmpDir . '/*.{csv,CSV}', GLOB_BRACE) as $file) {
            // Nur Dateien der letzten 10 Minuten
            if (filemtime($file) > time() - 600) {
                $files[] = $file;
            }
        }

        // Nach Aenderungszeit sortieren (neueste zuerst)
        usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));

        return $files;
    }

    /**
     * CSV-Datei und Temp-Dateien loeschen.
     */
    private function cleanupFile(string $fullPath): void
    {
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
        $jsonPath = $fullPath . '.json';
        if (file_exists($jsonPath)) {
            @unlink($jsonPath);
        }

        // Alte Temp-Dateien aufraeumen (aelter als 1 Stunde)
        foreach (['app/private/livewire-tmp', 'app/private/temp/data-imports'] as $dir) {
            $dirPath = storage_path($dir);
            if (is_dir($dirPath)) {
                foreach (glob($dirPath . '/*') as $file) {
                    if (is_file($file) && filemtime($file) < time() - 3600) {
                        @unlink($file);
                    }
                }
            }
        }
    }

    /**
     * Import-Historie als HTML.
     */
    private function getHistoryHtml(): HtmlString
    {
        $tenantId = auth()->user()?->tenant_id;

        $imports = ArticleImport::whereHas('gasStation', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })
            ->with('gasStation')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        if ($imports->isEmpty()) {
            return new HtmlString('<span style="color:#6b7280;">Noch keine Imports durchgeführt.</span>');
        }

        $html = '<div style="overflow-x:auto;"><table style="width:100%; font-size:0.85rem; border-collapse:collapse;">';
        $html .= '<thead><tr style="border-bottom:2px solid #e5e7eb; text-align:left;">';
        $html .= '<th style="padding:8px;">Status</th>';
        $html .= '<th style="padding:8px;">Datum</th>';
        $html .= '<th style="padding:8px;">Tankstelle</th>';
        $html .= '<th style="padding:8px;">Datei</th>';
        $html .= '<th style="padding:8px;">CSV-Stand</th>';
        $html .= '<th style="padding:8px;">Ergebnis</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($imports as $imp) {
            $status = match ($imp->status) {
                'completed' => '<span style="color:#16a34a;">✅ Fertig</span>',
                'failed' => '<span style="color:#dc2626;">❌ Fehler</span>',
                'processing' => '<span style="color:#f59e0b;">⏳ Läuft</span>',
                default => '<span style="color:#6b7280;">⏸️ Warten</span>',
            };

            $html .= '<tr style="border-bottom:1px solid #f3f4f6;">';
            $html .= "<td style='padding:8px;'>{$status}</td>";
            $html .= "<td style='padding:8px;'>{$imp->created_at->format('d.m.Y H:i')}</td>";
            $html .= "<td style='padding:8px;'>{$imp->gasStation->name}</td>";
            $html .= "<td style='padding:8px;'>{$imp->filename}</td>";
            $html .= "<td style='padding:8px;'>" . ($imp->csv_printed_at?->format('d.m.Y H:i') ?? '-') . "</td>";
            $html .= "<td style='padding:8px;'>{$imp->summary}</td>";
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';

        return new HtmlString($html);
    }
}
