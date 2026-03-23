<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\InvoiceResource\Pages;
use App\Mail\InvoiceMail;
use App\Models\GasStation;
use App\Models\Invoice;
use Filament\Actions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Partner-Resource fuer Rechnungsverwaltung.
 * ZUGFeRD-Rechnungen aus Kassensystem-Import.
 */
class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Rechnungen';

    protected static ?string $modelLabel = 'Rechnung';

    protected static ?string $pluralModelLabel = 'Rechnungen';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function getNavigationBadge(): ?string
    {
        $tenantId = auth()->user()?->tenant_id;
        if (! $tenantId) {
            return null;
        }

        $count = Invoice::whereHas('gasStation', fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'processed')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = auth()->user()?->tenant_id;

        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->whereHas('gasStation', fn (Builder $q) => $q->where('tenant_id', $tenantId))
            ->with(['gasStation', 'corporateCustomer']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Rechnung')
                ->tabs([
                    // ========== TAB 1: RECHNUNG ==========
                    Tab::make('Rechnung')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Section::make('Rechnungsdaten')
                                ->schema([
                                    TextInput::make('invoice_number')
                                        ->label('Rechnungsnummer')
                                        ->disabled(),

                                    TextInput::make('invoice_date')
                                        ->label('Rechnungsdatum')
                                        ->disabled()
                                        ->formatStateUsing(fn ($state) => $state instanceof \Carbon\Carbon ? $state->format('d.m.Y') : $state),

                                    TextInput::make('amount')
                                        ->label('Bruttobetrag')
                                        ->disabled()
                                        ->prefix('EUR'),

                                    TextInput::make('net_amount')
                                        ->label('Nettobetrag')
                                        ->disabled()
                                        ->prefix('EUR'),

                                    TextInput::make('tax_amount')
                                        ->label('MwSt')
                                        ->disabled()
                                        ->prefix('EUR'),

                                    Select::make('status')
                                        ->label('Status')
                                        ->options([
                                            'uploaded' => 'Hochgeladen',
                                            'processed' => 'Verarbeitet',
                                            'sent' => 'Versendet',
                                            'printed' => 'Gedruckt',
                                            'failed' => 'Fehlgeschlagen',
                                        ])
                                        ->disabled(),
                                ])
                                ->columns(3),

                            Section::make('PDF')
                                ->schema([
                                    Placeholder::make('pdf_link')
                                        ->label('')
                                        ->content(function (?Invoice $record): HtmlString {
                                            if (! $record || ! $record->pdf_path || ! Storage::exists($record->pdf_path)) {
                                                return new HtmlString('<span style="color:#9ca3af;">Kein PDF vorhanden</span>');
                                            }

                                            return new HtmlString('<span style="font-size:1.2rem;">📄 PDF verfuegbar: ' . basename($record->pdf_path) . '</span>');
                                        }),
                                ]),
                        ]),

                    // ========== TAB 2: KUNDE ==========
                    Tab::make('Kunde')
                        ->icon('heroicon-o-building-office')
                        ->schema([
                            Placeholder::make('customer_info')
                                ->label('')
                                ->content(function (?Invoice $record): HtmlString {
                                    if (! $record || ! $record->corporateCustomer) {
                                        return new HtmlString('<span style="color:#dc2626;">⚠️ Kein Kunde zugeordnet</span>');
                                    }
                                    $c = $record->corporateCustomer;
                                    $html = "<div style='padding:12px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px;'>";
                                    $html .= "<strong style='font-size:1.1rem;'>{$c->display_name}</strong><br>";
                                    $html .= "Firmen-Nr.: {$c->customer_number}<br>";
                                    if ($c->street) {
                                        $html .= "{$c->street}, {$c->zip} {$c->city}<br>";
                                    }
                                    if ($c->email) {
                                        $html .= "📧 {$c->email}<br>";
                                    }
                                    $html .= "Zahlart: {$c->payment_method}<br>";
                                    $sendMethod = [];
                                    if ($c->send_via_email) {
                                        $sendMethod[] = '📧 E-Mail';
                                    }
                                    if ($c->send_via_print) {
                                        $sendMethod[] = '🖨️ Druck';
                                    }
                                    $html .= 'Versand: ' . (! empty($sendMethod) ? implode(' + ', $sendMethod) : 'Nicht konfiguriert');
                                    $html .= '</div>';

                                    return new HtmlString($html);
                                }),
                        ]),

                    // ========== TAB 3: POSITIONEN ==========
                    Tab::make('Positionen')
                        ->icon('heroicon-o-list-bullet')
                        ->schema([
                            Placeholder::make('items_table')
                                ->label('')
                                ->content(function (?Invoice $record): HtmlString {
                                    if (! $record) {
                                        return new HtmlString('');
                                    }

                                    $items = $record->items;

                                    if ($items->isEmpty()) {
                                        return new HtmlString('<span style="color:#9ca3af;">Keine Positionen vorhanden</span>');
                                    }

                                    $html = '<table style="width:100%; border-collapse:collapse; font-size:0.9rem;">';
                                    $html .= '<thead><tr style="border-bottom:2px solid #e5e7eb;">';
                                    $html .= '<th style="padding:8px; text-align:left;">Artikel</th>';
                                    $html .= '<th style="padding:8px; text-align:right;">Menge</th>';
                                    $html .= '<th style="padding:8px; text-align:right;">Einzelpreis</th>';
                                    $html .= '<th style="padding:8px; text-align:right;">Gesamt</th>';
                                    $html .= '</tr></thead><tbody>';

                                    foreach ($items as $item) {
                                        $html .= '<tr style="border-bottom:1px solid #f3f4f6;">';
                                        $html .= '<td style="padding:6px 8px;">' . ($item->article ?? '-') . '</td>';
                                        $html .= '<td style="padding:6px 8px; text-align:right;">' . number_format($item->quantity, 3, ',', '.') . '</td>';
                                        $html .= '<td style="padding:6px 8px; text-align:right;">' . number_format($item->unit_price, 4, ',', '.') . ' &euro;</td>';
                                        $html .= '<td style="padding:6px 8px; text-align:right; font-weight:bold;">' . number_format($item->total_price, 2, ',', '.') . ' &euro;</td>';
                                        $html .= '</tr>';
                                    }

                                    $html .= '</tbody></table>';

                                    return new HtmlString($html);
                                }),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $tenantId = auth()->user()?->tenant_id;

        return $table
            ->columns([
                TextColumn::make('invoice_number')
                    ->label('Rechnungsnr.')
                    ->sortable()
                    ->searchable()
                    ->fontFamily('mono'),

                TextColumn::make('corporateCustomer.name')
                    ->label('Kunde')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('gasStation.name')
                    ->label('Tankstelle')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('amount')
                    ->label('Betrag')
                    ->money('EUR')
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'uploaded' => 'Hochgeladen',
                        'processed' => 'Verarbeitet',
                        'sent' => 'Versendet',
                        'printed' => 'Gedruckt',
                        'failed' => 'Fehlgeschlagen',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'uploaded' => 'gray',
                        'processed' => 'warning',
                        'sent' => 'success',
                        'printed' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('invoice_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('import_error_message')
                    ->label('Fehler')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('danger'),
            ])
            ->defaultSort('invoice_date', 'desc')
            ->filters([
                SelectFilter::make('gas_station_id')
                    ->label('Tankstelle')
                    ->options(function () use ($tenantId) {
                        return GasStation::where('tenant_id', $tenantId)->pluck('name', 'id');
                    }),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'uploaded' => 'Hochgeladen',
                        'processed' => 'Verarbeitet',
                        'sent' => 'Versendet',
                        'printed' => 'Gedruckt',
                        'failed' => 'Fehlgeschlagen',
                    ]),

                SelectFilter::make('import_status')
                    ->label('Import-Status')
                    ->options([
                        'success' => 'Erfolgreich',
                        'error' => 'Fehler',
                        'duplicate' => 'Duplikat',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('send_email')
                    ->label('E-Mail')
                    ->icon('heroicon-o-envelope')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Rechnung per E-Mail senden?')
                    ->modalDescription(fn (Invoice $record): string => "Rechnung {$record->invoice_number} an {$record->corporateCustomer?->email} senden?")
                    ->visible(fn (Invoice $record): bool => $record->corporateCustomer?->email && $record->pdf_path && Storage::exists($record->pdf_path))
                    ->action(function (Invoice $record) {
                        try {
                            Mail::to($record->corporateCustomer->email)
                                ->send(new InvoiceMail($record));

                            $record->update([
                                'status' => 'sent',
                                'sent_at' => now(),
                            ]);

                            Notification::make()->title('E-Mail versendet!')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Fehler: ' . $e->getMessage())->danger()->send();
                        }
                    }),
                Actions\Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (Invoice $record): bool => $record->pdf_path && Storage::exists($record->pdf_path))
                    ->url(fn (Invoice $record): string => Storage::url($record->pdf_path))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
