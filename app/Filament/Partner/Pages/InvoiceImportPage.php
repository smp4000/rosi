<?php

namespace App\Filament\Partner\Pages;

use App\Models\GasStation;
use App\Models\InvoiceBatch;
use App\Services\InvoiceBatchService;
use App\Services\ZugferdParserService;
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
 * Import-Seite fuer ZUGFeRD-PDF-Rechnungen.
 * Multi-Upload mit automatischer Batch-Verarbeitung.
 */
class InvoiceImportPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationLabel = 'Rechnungen importieren';

    protected static ?string $title = 'Rechnungen importieren';

    protected static ?string $slug = 'invoice-import';

    protected static string|\UnitEnum|null $navigationGroup = 'Rechnungen';

    public function getTitle(): string { return __('partner.invoice_import.title'); }
    public static function getNavigationLabel(): string { return __('partner.invoice_import.title'); }
    public static function getNavigationGroup(): ?string { return __('partner.invoice.nav_group'); }

    protected static ?int $navigationSort = 21;

    protected string $view = 'filament.partner.pages.invoice-import';

    public array $data = [];
    public ?InvoiceBatch $lastBatch = null;

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make(__('partner.invoice_import.section'))
                ->description(__('partner.invoice_import.section_desc'))
                ->schema([
                    FileUpload::make('pdf_files')
                        ->label(__('partner.invoice_import.field_label'))
                        ->acceptedFileTypes(['application/pdf', '.pdf'])
                        ->disk('local')
                        ->directory('temp/invoice-imports')
                        ->maxSize(10240)
                        ->multiple()
                        ->required()
                        ->helperText(__('partner.invoice_import.field_hint')),
                ])
                ->columns(1),

            // Ergebnis des letzten Imports
            Section::make(__('partner.invoice_import.last_import'))
                ->schema([
                    Placeholder::make('batch_result')
                        ->label('')
                        ->content(fn () => $this->getBatchResultHtml()),
                ])
                ->visible(fn () => $this->lastBatch !== null),

            // Import-Historie
            Section::make(__('partner.invoice_import.last_imports'))
                ->description(__('partner.invoice_import.last_imports_desc'))
                ->schema([
                    Placeholder::make('import_history')
                        ->label('')
                        ->content(fn () => $this->getHistoryHtml()),
                ])
                ->collapsible(),
        ]);
    }

    /**
     * Import starten.
     */
    public function importPdfs(): void
    {
        $pdfFiles = $this->data['pdf_files'] ?? [];

        if (empty($pdfFiles)) {
            Notification::make()->title(__('partner.invoice_import.messages.no_files'))->danger()->send();

            return;
        }

        // PDF-Pfade aufloesen
        $pdfPaths = [];
        $files = is_array($pdfFiles) ? $pdfFiles : [$pdfFiles];

        foreach ($files as $fileRef) {
            $fullPath = null;

            if ($fileRef instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                $fullPath = $fileRef->getRealPath();
            } else {
                $fullPath = $this->resolveFilePath($fileRef);
                if (! $fullPath) {
                    // Fallback: Neueste PDFs aus livewire-tmp
                    $recentFiles = $this->getRecentPdfFiles();
                    foreach ($recentFiles as $rf) {
                        $alreadyUsed = in_array($rf, $pdfPaths);
                        if (! $alreadyUsed) {
                            $fullPath = $rf;
                            break;
                        }
                    }
                }
            }

            if ($fullPath && file_exists($fullPath)) {
                $pdfPaths[] = $fullPath;
            }
        }

        if (empty($pdfPaths)) {
            Notification::make()->title(__('partner.invoice_import.messages.no_valid_pdfs'))->danger()->send();

            return;
        }

        // Tankstelle automatisch aus erster PDF erkennen
        $parser = new ZugferdParserService();
        $gasStationId = null;
        $stationName = null;

        foreach ($pdfPaths as $path) {
            $zugferdData = $parser->parseZugferdData($path);
            if ($zugferdData && isset($zugferdData['station'])) {
                $gasStationId = $zugferdData['station']->id;
                $stationName = $zugferdData['station']->name;
                break;
            }
        }

        if (! $gasStationId) {
            Notification::make()
                ->title(__('partner.invoice_import.messages.station_not_found'))
                ->body(__('partner.invoice_import.messages.station_not_found_desc'))
                ->danger()
                ->send();

            return;
        }

        // Batch erstellen
        $batch = InvoiceBatch::create([
            'gas_station_id' => $gasStationId,
            'name' => 'Import ' . now()->format('d.m.Y H:i') . ' - ' . $stationName,
            'source' => 'browser_upload',
            'total_files' => count($pdfPaths),
            'status' => 'pending',
            'imported_by' => auth()->id(),
        ]);

        // Verarbeitung starten
        try {
            $parser = new ZugferdParserService();
            $service = new InvoiceBatchService($parser);
            $service->processBatch($batch, $pdfPaths);

            $batch->refresh();
            $this->lastBatch = $batch;

            if ($batch->error_count > 0) {
                Notification::make()
                    ->title(__('partner.invoice_import.messages.import_partial'))
                    ->body($batch->summary)
                    ->warning()
                    ->send();
            } else {
                Notification::make()
                    ->title(__('partner.invoice_import.messages.import_success'))
                    ->body($batch->summary)
                    ->success()
                    ->send();
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('partner.invoice_import.messages.import_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            // Temp-Dateien aufraeumen (auch bei Exception)
            foreach ($pdfPaths as $path) {
                if (file_exists($path)) {
                    @unlink($path);
                }
            }

            $this->data['pdf_files'] = [];

            // Alte Temp-Dateien aufraeumen (aelter als 1 Stunde)
            $this->cleanupOldTempFiles();
        }

        // Nach Import zur Batch-Detail-Seite weiterleiten
        if (isset($batch) && $batch->id) {
            $this->redirect('/dashboard/invoice-batches/' . $batch->id);
        }
    }

    /**
     * Dateipfad aufloesen.
     */
    private function resolveFilePath(mixed $fileRef): ?string
    {
        $filePath = is_array($fileRef) ? reset($fileRef) : $fileRef;

        $candidates = [
            storage_path('app/private/' . $filePath),
            storage_path('app/' . $filePath),
            storage_path('app/private/livewire-tmp/' . $filePath),
            storage_path('app/livewire-tmp/' . $filePath),
            storage_path('app/private/temp/invoice-imports/' . $filePath),
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Neueste PDF-Dateien aus livewire-tmp holen (Fallback).
     */
    private function getRecentPdfFiles(): array
    {
        $dirs = [
            storage_path('app/private/livewire-tmp'),
            storage_path('app/private/temp/invoice-imports'),
        ];

        $files = [];
        foreach ($dirs as $tmpDir) {
            if (! is_dir($tmpDir)) {
                continue;
            }
            foreach (glob($tmpDir . '/*.{pdf,PDF}', GLOB_BRACE) as $file) {
                // Nur Dateien der letzten 10 Minuten
                if (filemtime($file) > time() - 600) {
                    $files[] = $file;
                }
            }
        }

        // Nach Aenderungszeit sortieren (neueste zuerst)
        usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));

        return $files;
    }

    /**
     * Batch-Ergebnis als HTML.
     */
    private function getBatchResultHtml(): HtmlString
    {
        if (! $this->lastBatch) {
            return new HtmlString('');
        }

        $b = $this->lastBatch;
        $bgColor = $b->error_count > 0 ? '#fffbeb' : '#f0fdf4';
        $borderColor = $b->error_count > 0 ? '#fde68a' : '#bbf7d0';

        $html = "<div style='padding:16px; background:{$bgColor}; border:1px solid {$borderColor}; border-radius:8px;'>";
        $html .= "<div style='display:flex; gap:24px; flex-wrap:wrap;'>";

        $stats = [
            ['label' => 'Gesamt', 'value' => $b->total_files, 'icon' => '📄'],
            ['label' => 'Erfolgreich', 'value' => $b->success_count, 'icon' => '✅'],
            ['label' => 'Fehler', 'value' => $b->error_count, 'icon' => '❌'],
            ['label' => 'Duplikate', 'value' => $b->duplicate_count, 'icon' => '🔄'],
            ['label' => 'Neue Kunden', 'value' => $b->new_customers_count, 'icon' => '👤'],
        ];

        foreach ($stats as $stat) {
            $html .= "<div style='text-align:center;'>";
            $html .= "<div style='font-size:1.5rem; font-weight:bold;'>{$stat['icon']} {$stat['value']}</div>";
            $html .= "<div style='font-size:0.8rem; color:#6b7280;'>{$stat['label']}</div>";
            $html .= '</div>';
        }

        $html .= '</div></div>';

        return new HtmlString($html);
    }

    /**
     * Import-Historie als HTML.
     */
    private function getHistoryHtml(): HtmlString
    {
        $tenantId = auth()->user()?->tenant_id;

        $batches = InvoiceBatch::whereHas('gasStation', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        if ($batches->isEmpty()) {
            return new HtmlString('<span style="color:#6b7280;">Noch keine Rechnungs-Imports durchgefuehrt.</span>');
        }

        $html = '<table style="width:100%; font-size:0.85rem; border-collapse:collapse;">';
        $html .= '<thead><tr style="border-bottom:2px solid #e5e7eb;">';
        $html .= '<th style="padding:8px;">Status</th>';
        $html .= '<th style="padding:8px;">Datum</th>';
        $html .= '<th style="padding:8px;">Gesamt</th>';
        $html .= '<th style="padding:8px;">Erfolgreich</th>';
        $html .= '<th style="padding:8px;">Fehler</th>';
        $html .= '<th style="padding:8px;">Neue Kunden</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($batches as $b) {
            $status = match ($b->status) {
                'completed' => '<span style="color:#16a34a;">✅</span>',
                'failed' => '<span style="color:#dc2626;">❌</span>',
                'processing' => '<span style="color:#f59e0b;">⏳</span>',
                default => '<span style="color:#6b7280;">⏸️</span>',
            };

            $html .= '<tr style="border-bottom:1px solid #f3f4f6;">';
            $html .= "<td style='padding:8px;'>{$status}</td>";
            $html .= "<td style='padding:8px;'>{$b->created_at->format('d.m.Y H:i')}</td>";
            $html .= "<td style='padding:8px; text-align:center;'>{$b->total_files}</td>";
            $html .= "<td style='padding:8px; text-align:center; color:#16a34a;'>{$b->success_count}</td>";
            $html .= "<td style='padding:8px; text-align:center; color:#dc2626;'>" . ($b->error_count > 0 ? $b->error_count : '-') . '</td>';
            $html .= "<td style='padding:8px; text-align:center; color:#2563eb;'>" . ($b->new_customers_count > 0 ? $b->new_customers_count : '-') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return new HtmlString($html);
    }

    /**
     * Raeumt Temp-Dateien auf, die aelter als 1 Stunde sind.
     */
    private function cleanupOldTempFiles(): void
    {
        $dirs = [
            storage_path('app/private/livewire-tmp'),
            storage_path('app/private/temp/invoice-imports'),
        ];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            $files = glob($dir . '/*');
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < time() - 3600) {
                    @unlink($file);
                }
            }
        }
    }
}
