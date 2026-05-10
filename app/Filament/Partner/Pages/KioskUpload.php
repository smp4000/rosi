<?php

namespace App\Filament\Partner\Pages;

use App\Models\Kiosk\Article;
use App\Models\Kiosk\Import;
use App\Models\Kiosk\Invoice;
use App\Models\Kiosk\OrderLine;
use App\Models\Kiosk\PriceChangeLog;
use App\Services\Kiosk\PvgPdfParserService;
use App\Services\Kiosk\ZugferdInvoiceParserService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Kiosk-Dashboard mit KPIs, PDF-Upload und letzte Importe.
 */
class KioskUpload extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Zeitungen-Dashboard';

    protected static ?string $slug = 'kiosk';

    protected static string|\UnitEnum|null $navigationGroup = 'Zeitungen';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.partner.pages.kiosk-upload';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->statePath('data')
            ->components([
                Section::make('PVG-Rechnung importieren')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->description('PDF-Datei hochladen — Artikel, Preise und Bestellzeilen werden automatisch extrahiert.')
                    ->schema([
                        FileUpload::make('pdf')
                            ->label('PDF-Datei')
                            ->disk('local')
                            ->directory('kiosk-uploads')
                            ->acceptedFileTypes(['application/pdf', '.pdf'])
                            ->maxSize(25 * 1024)
                            ->required(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importPdf')
                ->label('PDF importieren')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->action('runImport'),
        ];
    }

    public function runImport(): void
    {
        try {
            $pdfRef = $this->data['pdf'] ?? null;

            if (empty($pdfRef)) {
                Notification::make()->title('Keine Datei ausgewaehlt')->danger()->send();
                return;
            }

            // FileUpload kann ein Array, String oder TemporaryUploadedFile sein.
            $absolutePath = $this->resolvePath($pdfRef);
            if (! $absolutePath || ! file_exists($absolutePath)) {
                \Illuminate\Support\Facades\Log::warning('Kiosk Upload: Datei nicht gefunden', [
                    'pdf' => $pdfRef,
                    'data' => $this->data,
                ]);
                Notification::make()
                    ->title('Datei nicht gefunden')
                    ->body('Form-State: ' . json_encode($pdfRef))
                    ->danger()
                    ->persistent()
                    ->send();
                return;
            }

            $originalName = basename($absolutePath);
            $tenantId = auth()->user()->tenant_id;

            // ZUGFeRD/Factur-X bevorzugen wenn eingebettetes XML vorhanden
            $zugferd = app(ZugferdInvoiceParserService::class);
            if ($zugferd->hasEmbeddedXml($absolutePath)) {
                $result = $zugferd->import($absolutePath, $tenantId, $originalName);
            } else {
                $parser = app(PvgPdfParserService::class);
                $result = $parser->import($absolutePath, $tenantId, $originalName);
            }

            if ($result['success']) {
                if ($result['articles_inserted'] == 0 && $result['articles_updated'] == 0) {
                    Notification::make()
                        ->title('Import abgeschlossen — aber keine Positionen erkannt')
                        ->body('Das PDF-Layout passt eventuell nicht zum erwarteten PVG-Format. Pruefe storage/logs/laravel.log fuer Debug-Ausgabe.')
                        ->warning()
                        ->persistent()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Import erfolgreich')
                        ->body("Eingefuegt: {$result['articles_inserted']}, Aktualisiert: {$result['articles_updated']}")
                        ->success()
                        ->send();
                }
                $this->form->fill();
            } else {
                Notification::make()
                    ->title('Import nicht abgeschlossen')
                    ->body($result['message'] ?? 'Unbekannter Fehler')
                    ->warning()
                    ->persistent()
                    ->send();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Kiosk PDF Upload', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            Notification::make()
                ->title('Fehler beim Upload')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    private function resolvePath(mixed $pdfRef): ?string
    {
        // Direkter TemporaryUploadedFile
        if ($pdfRef instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            return $pdfRef->getRealPath();
        }

        // Wert kann auch in Array stecken
        if (is_array($pdfRef)) {
            foreach ($pdfRef as $entry) {
                if ($entry instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                    return $entry->getRealPath();
                }
                if (is_string($entry) && $entry !== '') {
                    $resolved = $this->resolveString($entry);
                    if ($resolved) return $resolved;
                }
            }
        } elseif (is_string($pdfRef) && $pdfRef !== '') {
            $resolved = $this->resolveString($pdfRef);
            if ($resolved) return $resolved;
        }

        // Letzter Fallback: neueste PDF in livewire-tmp
        return $this->getRecentLivewirePdf();
    }

    private function resolveString(string $value): ?string
    {
        $candidates = [
            storage_path('app/private/' . $value),
            storage_path('app/' . $value),
            storage_path('app/private/kiosk-uploads/' . basename($value)),
            storage_path('app/private/livewire-tmp/' . $value),
            storage_path('app/livewire-tmp/' . $value),
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function getRecentLivewirePdf(): ?string
    {
        $dirs = [
            storage_path('app/private/livewire-tmp'),
            storage_path('app/livewire-tmp'),
        ];

        $newest = null;
        $newestTime = 0;
        foreach ($dirs as $dir) {
            if (! is_dir($dir)) continue;
            foreach (glob($dir . '/*.pdf') ?: [] as $file) {
                $mtime = filemtime($file);
                if ($mtime > $newestTime && (time() - $mtime) < 300) { // max 5 Min alt
                    $newest = $file;
                    $newestTime = $mtime;
                }
            }
        }
        return $newest;
    }

    public function getKpisProperty(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'invoices' => Invoice::where('tenant_id', $tenantId)->count(),
            'articles' => Article::where('tenant_id', $tenantId)->count(),
            'pending' => Article::where('tenant_id', $tenantId)->where('is_pending', true)->count(),
            'order_lines' => OrderLine::whereHas('invoice', fn ($q) => $q->where('tenant_id', $tenantId))->count(),
            'price_changes' => PriceChangeLog::whereHas('article', fn ($q) => $q->where('tenant_id', $tenantId))->count(),
        ];
    }

    public function getRecentImportsProperty()
    {
        return Import::where('tenant_id', auth()->user()->tenant_id)
            ->latest('created_at')
            ->limit(10)
            ->get();
    }

    public function getRecentPriceChangesProperty()
    {
        return PriceChangeLog::whereHas('article', fn ($q) => $q->where('tenant_id', auth()->user()->tenant_id))
            ->with('article')
            ->latest('changed_at')
            ->limit(10)
            ->get();
    }
}
