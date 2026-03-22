<?php

namespace App\Filament\Partner\Pages;

use App\Models\ArticleImport;
use App\Models\GasStation;
use App\Services\ArticleCsvImportService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;

/**
 * Universelle Import-Seite im Partner-Panel.
 * Erkennt den Dateityp automatisch anhand der ersten Zeilen.
 * Spaeter erweiterbar fuer verschiedene CSV-Typen (Loeschliste, EAN, etc.).
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

    // Form-State (array weil Filament FileUpload so arbeitet)
    public array $data = [];

    // Erkannte Metadaten (nach Upload)
    public string $detected_type = '';
    public string $detected_type_label = '';
    public string $detected_station_number = '';
    public string $detected_station_name = '';
    public string $detected_print_date = '';
    public int $detected_line_count = 0;
    public bool $file_analyzed = false;
    public string $analysis_error = '';

    /**
     * Bekannte Dateitypen und ihre Erkennungsmuster.
     * Spaeter einfach erweiterbar.
     */
    private static function getFilePatterns(): array
    {
        return [
            'ean_data' => [
                'label' => 'Artikel-EAN-Daten (Mengenverordnung)',
                'pattern' => 'Mengenverordnung',
                'icon' => '🏷️',
            ],
            'articles' => [
                'label' => 'Artikelstammdaten (ArtDat-Export)',
                'pattern' => 'Nr.,,Bezeichnung,Einheit,,akt.EK',
                'icon' => '📦',
            ],
            // Spaeter:
            // 'delete_list' => [
            //     'label' => 'Löschliste',
            //     'pattern' => 'Löschliste',
            //     'icon' => '🗑️',
            // ],
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('CSV-Datei hochladen')
                ->description('Laden Sie eine CSV-Datei hoch. Der Dateityp wird automatisch erkannt.')
                ->schema([
                    FileUpload::make('csv_file')
                        ->label('CSV-Datei')
                        ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain', '.csv'])
                        ->disk('local')
                        ->directory('temp/data-imports')
                        ->maxSize(10240)
                        ->required()
                        ->helperText('CSV-Export aus dem Kassensystem (max. 10 MB)')
                        ->live()
                        ->afterStateUpdated(function ($state, \Filament\Forms\Components\FileUpload $component) {
                            if ($state) {
                                // Filament FileUpload: State kann TemporaryUploadedFile oder String sein
                                $files = is_array($state) ? $state : [$state];
                                $file = reset($files);

                                $fullPath = null;

                                if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                    $fullPath = $file->getRealPath();
                                } else {
                                    // String-Pfad: In verschiedenen Verzeichnissen suchen
                                    $candidates = [
                                        storage_path('app/private/livewire-tmp/' . $file),
                                        storage_path('app/private/' . $file),
                                        storage_path('app/livewire-tmp/' . $file),
                                        storage_path('app/' . $file),
                                    ];

                                    // Auch mit Glob suchen (Livewire hasht den Namen)
                                    $globResults = glob(storage_path('app/private/livewire-tmp/*'));

                                    foreach ($candidates as $candidate) {
                                        if (file_exists($candidate)) {
                                            $fullPath = $candidate;
                                            break;
                                        }
                                    }

                                    // Fallback: Neueste Datei im livewire-tmp
                                    if (! $fullPath && ! empty($globResults)) {
                                        usort($globResults, fn ($a, $b) => filemtime($b) - filemtime($a));
                                        // Nur CSV/csv Dateien
                                        foreach ($globResults as $g) {
                                            if (preg_match('/\.(csv|CSV)$/i', $g)) {
                                                $fullPath = $g;
                                                break;
                                            }
                                        }
                                    }
                                }

                                if ($fullPath && file_exists($fullPath)) {
                                    $this->analyzeFile($fullPath);
                                } else {
                                    $this->analysis_error = 'Datei konnte nicht gefunden werden. Bitte erneut hochladen.';
                                    $this->file_analyzed = true;
                                }
                            } else {
                                $this->resetAnalysis();
                            }
                        }),
                ])
                ->columns(1),

            // Analyse-Ergebnis (nur sichtbar nach Upload)
            Section::make('Erkannte Datei-Informationen')
                ->schema([
                    Placeholder::make('analysis_result')
                        ->label('')
                        ->content(fn () => $this->getAnalysisHtml()),
                ])
                ->visible(fn () => $this->file_analyzed)
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
     * Datei analysieren und Typ erkennen.
     */
    public function analyzeFile(?string $fullPath): void
    {
        $this->resetAnalysis();

        if (! $fullPath || ! file_exists($fullPath)) {
            $this->analysis_error = 'Datei nicht gefunden.';
            $this->file_analyzed = true;
            return;
        }

        try {
            // Erste 50 Zeilen lesen fuer Erkennung
            $content = file_get_contents($fullPath);
            if (! mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
            }

            $lines = explode("\n", $content);
            $headerLines = array_slice($lines, 0, 50);
            $headerText = implode("\n", $headerLines);

            // Dateityp erkennen
            foreach (self::getFilePatterns() as $type => $config) {
                if (str_contains($headerText, $config['pattern'])) {
                    $this->detected_type = $type;
                    $this->detected_type_label = $config['icon'] . ' ' . $config['label'];
                    break;
                }
            }

            if (! $this->detected_type) {
                $this->analysis_error = 'Unbekannter Dateityp. Die CSV-Datei konnte keinem bekannten Format zugeordnet werden.';
                $this->file_analyzed = true;
                return;
            }

            // Metadaten extrahieren
            foreach ($lines as $line) {
                if (preg_match('/Stationsnummer:\s*(\d+)/', $line, $m)) {
                    $this->detected_station_number = $m[1];
                }
                if (preg_match('/Druckdatum:\s*(\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2})/', $line, $m)) {
                    $this->detected_print_date = $m[1];
                }
                if ($this->detected_station_number && $this->detected_print_date) {
                    break;
                }
            }

            // Tankstelle suchen
            $tenantId = auth()->user()?->tenant_id;
            if ($this->detected_station_number) {
                // Fuehrende Nullen entfernen, LIKE-Suche in allen Stationsnummer-Feldern
                $normalizedNr = ltrim($this->detected_station_number, '0');
                $station = GasStation::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($normalizedNr) {
                        $q->where('station_number', 'LIKE', '%' . $normalizedNr . '%')
                          ->orWhere('station_number_shop', 'LIKE', '%' . $normalizedNr . '%')
                          ->orWhere('station_number_fuel', 'LIKE', '%' . $normalizedNr . '%');
                    })
                    ->first();

                $this->detected_station_name = $station
                    ? $station->name
                    : '⚠️ Nicht gefunden (Stationsnummer: ' . $this->detected_station_number . ')';
            }

            $this->detected_line_count = count($lines);
            $this->file_analyzed = true;

        } catch (\Exception $e) {
            $this->analysis_error = 'Fehler beim Analysieren: ' . $e->getMessage();
            $this->file_analyzed = true;
        }
    }

    /**
     * Analyse zuruecksetzen.
     */
    private function resetAnalysis(): void
    {
        $this->detected_type = '';
        $this->detected_type_label = '';
        $this->detected_station_number = '';
        $this->detected_station_name = '';
        $this->detected_print_date = '';
        $this->detected_line_count = 0;
        $this->file_analyzed = false;
        $this->analysis_error = '';
    }

    /**
     * Analyse-Ergebnis als HTML.
     */
    private function getAnalysisHtml(): HtmlString
    {
        if (! empty($this->analysis_error)) {
            return new HtmlString(
                '<div style="padding:12px; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; color:#991b1b;">'
                . "❌ {$this->analysis_error}</div>"
            );
        }

        $rows = [
            ['Dateityp', $this->detected_type_label ?? '-'],
            ['Stationsnummer', $this->detected_station_number ?? '-'],
            ['Tankstelle', $this->detected_station_name ?? '-'],
            ['Druckdatum (CSV-Stand)', $this->detected_print_date ?? '-'],
            ['Zeilen', number_format($this->detected_line_count ?? 0, 0, ',', '.')],
        ];

        $stationFound = ! empty($this->detected_station_name) && ! str_starts_with($this->detected_station_name, '⚠️');
        $borderColor = $stationFound ? '#bbf7d0' : '#fde68a';
        $bgColor = $stationFound ? '#f0fdf4' : '#fffbeb';

        $html = "<div style='padding:12px; background:{$bgColor}; border:1px solid {$borderColor}; border-radius:8px;'>";
        $html .= '<table style="width:100%; font-size:0.9rem;">';
        foreach ($rows as [$label, $value]) {
            $html .= "<tr><td style='padding:4px 8px; font-weight:600; width:200px;'>{$label}</td><td style='padding:4px 8px;'>{$value}</td></tr>";
        }
        $html .= '</table>';

        if (! $stationFound) {
            $html .= '<div style="margin-top:8px; padding:8px; background:#fef3c7; border-radius:4px; font-size:0.85rem;">';
            $html .= '⚠️ Die Tankstelle wurde nicht gefunden. Bitte hinterlegen Sie die Stationsnummer in den Tankstellen-Stammdaten.';
            $html .= '</div>';
        }

        $html .= '</div>';

        return new HtmlString($html);
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

    /**
     * Import starten.
     */
    public function importCsv(): void
    {
        $csvFile = $this->data['csv_file'] ?? null;

        if (! $csvFile) {
            Notification::make()->title('Keine Datei ausgewählt')->danger()->send();
            return;
        }

        if (empty($this->detected_type)) {
            Notification::make()->title('Dateityp nicht erkannt')->body('Bitte laden Sie eine gültige CSV-Datei hoch.')->danger()->send();
            return;
        }

        $fullPath = $this->resolveFilePath($csvFile);

        if (! $fullPath) {
            Notification::make()->title('Datei nicht gefunden')->body('Die hochgeladene Datei konnte nicht gefunden werden. Bitte erneut hochladen.')->danger()->send();
            return;
        }

        try {
            // Je nach erkanntem Typ den passenden Service aufrufen
            $import = match ($this->detected_type) {
                'articles' => $this->importArticles($fullPath, basename($fullPath)),
                'ean_data' => $this->importEanData($fullPath, basename($fullPath)),
                // Spaeter:
                // 'delete_list' => $this->importDeleteList($fullPath, basename($fullPath)),
                default => throw new \Exception("Import-Typ '{$this->detected_type}' wird noch nicht unterstützt."),
            };

            Notification::make()
                ->title('Import erfolgreich!')
                ->body($import->summary)
                ->success()
                ->send();

            // CSV-Datei loeschen nach erfolgreichem Import
            $this->cleanupFile($fullPath);

            // Formular zuruecksetzen
            $this->data['csv_file'] = null;
            $this->resetAnalysis();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Import fehlgeschlagen')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Dateipfad aufloesen - sucht in allen moeglichen Verzeichnissen.
     */
    private function resolveFilePath(mixed $csvFile): ?string
    {
        $filePath = is_array($csvFile) ? reset($csvFile) : $csvFile;

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

        // Fallback: Neueste CSV im livewire-tmp
        $globResults = glob(storage_path('app/private/livewire-tmp/*.{csv,CSV}'), GLOB_BRACE);
        if (! empty($globResults)) {
            usort($globResults, fn ($a, $b) => filemtime($b) - filemtime($a));
            return $globResults[0];
        }

        return null;
    }

    /**
     * Artikelstammdaten importieren.
     */
    private function importArticles(string $fullPath, string $filename): ArticleImport
    {
        $service = new ArticleCsvImportService();

        return $service->import($fullPath, $filename, auth()->id());
    }

    private function importEanData(string $fullPath, string $filename): ArticleImport
    {
        $service = new \App\Services\ArticleEanCsvImportService();

        return $service->import($fullPath, $filename, auth()->id());
    }

    /**
     * CSV-Datei und zugehoerige Livewire-Temp-Dateien loeschen.
     */
    private function cleanupFile(string $fullPath): void
    {
        // Hauptdatei loeschen
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }

        // Zugehoerige JSON-Metadaten loeschen (Livewire erstellt .json neben der Datei)
        $jsonPath = $fullPath . '.json';
        if (file_exists($jsonPath)) {
            @unlink($jsonPath);
        }

        // Alte Livewire-Temp-Dateien aufraeumen (aelter als 1 Stunde)
        $tmpDir = storage_path('app/private/livewire-tmp');
        if (is_dir($tmpDir)) {
            foreach (glob($tmpDir . '/*') as $file) {
                if (is_file($file) && filemtime($file) < time() - 3600) {
                    @unlink($file);
                }
            }
        }

        // Import-Temp-Verzeichnis aufraeumen
        $importDir = storage_path('app/private/temp/data-imports');
        if (is_dir($importDir)) {
            foreach (glob($importDir . '/*') as $file) {
                if (is_file($file) && filemtime($file) < time() - 3600) {
                    @unlink($file);
                }
            }
        }
    }
}
