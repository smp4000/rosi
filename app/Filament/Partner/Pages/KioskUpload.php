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
            $data = $this->form->getState();

            if (empty($data['pdf'])) {
                Notification::make()->title('Keine Datei ausgewaehlt')->danger()->send();
                return;
            }

            $tenantId = auth()->user()->tenant_id;
            // FileUpload kann ein Array oder String sein, manchmal mit Schlüsseln
            $pdfValue = $data['pdf'];
            if (is_array($pdfValue)) {
                $pdfValue = reset($pdfValue);
            }
            if (! is_string($pdfValue) || empty($pdfValue)) {
                Notification::make()
                    ->title('Datei-Pfad nicht erkannt')
                    ->body('Form-State: ' . json_encode($data['pdf']))
                    ->danger()
                    ->send();
                return;
            }

            $disk = \Illuminate\Support\Facades\Storage::disk('local');
            if (! $disk->exists($pdfValue)) {
                Notification::make()
                    ->title('Datei nicht gefunden')
                    ->body("Pfad: {$pdfValue}")
                    ->danger()
                    ->send();
                return;
            }

            $absolutePath = $disk->path($pdfValue);
            $originalName = basename($pdfValue);

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
