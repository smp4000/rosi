<?php

namespace App\Filament\Partner\Pages;

use App\Models\Kiosk\Article;
use App\Models\Kiosk\Import;
use App\Models\Kiosk\Invoice;
use App\Models\Kiosk\OrderLine;
use App\Models\Kiosk\PriceChangeLog;
use App\Services\Kiosk\PvgPdfParserService;
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
            ->schema([
                Section::make('PVG-Rechnung importieren')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->description('PDF-Datei hochladen — Artikel, Preise und Bestellzeilen werden automatisch extrahiert.')
                    ->schema([
                        FileUpload::make('pdf')
                            ->label('PDF-Datei')
                            ->disk('local')
                            ->directory('kiosk-uploads')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(25 * 1024)
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function upload(): void
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

            $parser = app(PvgPdfParserService::class);
            $result = $parser->import($absolutePath, $tenantId, $originalName);

            if ($result['success']) {
                Notification::make()
                    ->title('Import erfolgreich')
                    ->body("Eingefuegt: {$result['articles_inserted']}, Aktualisiert: {$result['articles_updated']}")
                    ->success()
                    ->send();
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
        if ($pdfRef instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            return $pdfRef->getRealPath();
        }

        $value = is_array($pdfRef) ? reset($pdfRef) : $pdfRef;
        if (! is_string($value) || $value === '') {
            return null;
        }

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
