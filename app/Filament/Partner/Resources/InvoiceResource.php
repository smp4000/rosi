<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\InvoiceResource\Pages;
use App\Mail\InvoiceMail;
use App\Models\GasStation;
use App\Models\Invoice;
use App\Models\InvoiceLog;
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
    use \App\Filament\Concerns\HasCatalogPermissions;

    /** Katalog-Schluessel fuer die Rechte-Pruefung (Rollen-Matrix) */
    protected static ?string $permissionKey = 'partner.invoices';

    protected static ?string $model = Invoice::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Rechnungen';

    protected static ?string $modelLabel = 'Rechnung';

    protected static ?string $pluralModelLabel = 'Rechnungen';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function getModelLabel(): string
    {
        return __('partner.invoice.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('partner.invoice.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('partner.invoice.nav_group');
    }

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
            Tabs::make(__('partner.invoice.label'))
                ->tabs([
                    // ========== TAB 1: RECHNUNG ==========
                    Tab::make(__('partner.invoice.tabs.rechnung'))
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            Section::make(__('partner.invoice.tabs.rechnung'))
                                ->schema([
                                    TextInput::make('invoice_number')
                                        ->label(__('partner.invoice.fields.invoice_number'))
                                        ->disabled(),

                                    TextInput::make('invoice_date')
                                        ->label(__('partner.invoice.fields.invoice_date'))
                                        ->disabled()
                                        ->formatStateUsing(fn ($state) => $state instanceof \Carbon\Carbon ? $state->format('d.m.Y') : $state),

                                    TextInput::make('amount')
                                        ->label(__('partner.invoice.fields.amount'))
                                        ->disabled()
                                        ->prefix('EUR'),

                                    TextInput::make('net_amount')
                                        ->label(__('partner.invoice.fields.net_amount'))
                                        ->disabled()
                                        ->prefix('EUR'),

                                    TextInput::make('tax_amount')
                                        ->label(__('partner.invoice.fields.tax_amount'))
                                        ->disabled()
                                        ->prefix('EUR'),

                                    Select::make('status')
                                        ->label(__('partner.invoice.fields.status'))
                                        ->options([
                                            'uploaded' => __('partner.invoice.statuses.uploaded'),
                                            'processed' => __('partner.invoice.statuses.processed'),
                                            'sent' => __('partner.invoice.statuses.sent'),
                                            'printed' => __('partner.invoice.statuses.printed'),
                                            'failed' => __('partner.invoice.statuses.failed'),
                                        ])
                                        ->disabled(),
                                ])
                                ->columns(3),

                            Section::make(__('partner.invoice.fields.pdf_path'))
                                ->schema([
                                    Placeholder::make('pdf_link')
                                        ->label('')
                                        ->content(function (?Invoice $record): HtmlString {
                                            if (! $record || ! $record->pdf_path || ! Storage::exists($record->pdf_path)) {
                                                return new HtmlString('<span style="color:#9ca3af;">' . __('partner.invoice.messages.no_pdf') . '</span>');
                                            }

                                            return new HtmlString('<span style="font-size:1.2rem;">📄 ' . __('partner.invoice.messages.pdf_available') . ': ' . basename($record->pdf_path) . '</span>');
                                        }),
                                ]),
                        ]),

                    // ========== TAB 2: KUNDE ==========
                    Tab::make(__('partner.invoice.tabs.kunde'))
                        ->icon('heroicon-o-building-office')
                        ->schema([
                            Placeholder::make('customer_info')
                                ->label('')
                                ->content(function (?Invoice $record): HtmlString {
                                    if (! $record || ! $record->corporateCustomer) {
                                        return new HtmlString('<span style="color:#dc2626;">⚠️ ' . __('partner.invoice.messages.no_customer') . '</span>');
                                    }
                                    $c = $record->corporateCustomer;
                                    $html = "<div style='padding:12px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px;'>";
                                    $html .= "<strong style='font-size:1.1rem;'>{$c->display_name}</strong><br>";
                                    $html .= __('partner.invoice.fields.customer_number') . ": {$c->customer_number}<br>";
                                    if ($c->street) {
                                        $html .= "{$c->street}, {$c->zip} {$c->city}<br>";
                                    }
                                    if ($c->email) {
                                        $html .= "📧 {$c->email}<br>";
                                    }
                                    $html .= __('partner.invoice.fields.payment_method') . ": {$c->payment_method}<br>";
                                    $sendMethod = [];
                                    if ($c->send_via_email) {
                                        $sendMethod[] = '📧 E-Mail';
                                    }
                                    if ($c->send_via_print) {
                                        $sendMethod[] = '🖨️ ' . __('partner.invoice.statuses.printed');
                                    }
                                    $html .= __('partner.invoice.fields.send_method') . ': ' . (! empty($sendMethod) ? implode(' + ', $sendMethod) : 'Nicht konfiguriert');
                                    $html .= '</div>';

                                    return new HtmlString($html);
                                }),
                        ]),

                    // ========== TAB 3: POSITIONEN ==========
                    Tab::make(__('partner.invoice.tabs.positionen'))
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
                                        return new HtmlString('<span style="color:#9ca3af;">' . __('partner.invoice.messages.no_items') . '</span>');
                                    }

                                    $html = '<table style="width:100%; border-collapse:collapse; font-size:0.9rem;">';
                                    $html .= '<thead><tr style="border-bottom:2px solid #e5e7eb;">';
                                    $html .= '<th style="padding:8px; text-align:left;">' . __('partner.invoice.fields.article') . '</th>';
                                    $html .= '<th style="padding:8px; text-align:right;">' . __('partner.invoice.fields.quantity') . '</th>';
                                    $html .= '<th style="padding:8px; text-align:right;">' . __('partner.invoice.fields.unit_price') . '</th>';
                                    $html .= '<th style="padding:8px; text-align:right;">' . __('partner.invoice.fields.total') . '</th>';
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
                    ->label(__('partner.invoice.fields.invoice_number'))
                    ->sortable()
                    ->searchable()
                    ->fontFamily('mono'),

                TextColumn::make('corporateCustomer.name')
                    ->label(__('partner.invoice.fields.customer'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('gasStation.name')
                    ->label(__('partner.invoice.fields.gas_station'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('amount')
                    ->label(__('partner.invoice.fields.betrag'))
                    ->money('EUR')
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label(__('partner.invoice.fields.status'))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'uploaded' => __('partner.invoice.statuses.uploaded'),
                        'processed' => __('partner.invoice.statuses.processed'),
                        'sent' => __('partner.invoice.statuses.sent'),
                        'printed' => __('partner.invoice.statuses.printed'),
                        'failed' => __('partner.invoice.statuses.failed'),
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
                    ->label(__('partner.invoice.fields.datum'))
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('import_error_message')
                    ->label(__('partner.invoice.fields.fehler'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('danger'),
            ])
            ->defaultSort('invoice_date', 'desc')
            ->filters([
                SelectFilter::make('gas_station_id')
                    ->label(__('partner.invoice.fields.gas_station'))
                    ->options(function () use ($tenantId) {
                        return GasStation::where('tenant_id', $tenantId)->pluck('name', 'id');
                    }),

                SelectFilter::make('status')
                    ->label(__('partner.invoice.fields.status'))
                    ->options([
                        'uploaded' => __('partner.invoice.statuses.uploaded'),
                        'processed' => __('partner.invoice.statuses.processed'),
                        'sent' => __('partner.invoice.statuses.sent'),
                        'printed' => __('partner.invoice.statuses.printed'),
                        'failed' => __('partner.invoice.statuses.failed'),
                    ]),

                SelectFilter::make('import_status')
                    ->label('Import-Status')
                    ->options([
                        'success' => __('partner.invoice.import_statuses.success'),
                        'error' => __('partner.invoice.import_statuses.error'),
                        'duplicate' => __('partner.invoice.import_statuses.duplicate'),
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('send_email')
                    ->label('E-Mail')
                    ->icon('heroicon-o-envelope')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(__('partner.invoice.messages.send_confirm'))
                    ->modalDescription(fn (Invoice $record): string => __('partner.invoice.messages.send_description', [
                        'number' => $record->invoice_number,
                        'email' => $record->corporateCustomer?->email,
                    ]))
                    ->visible(fn (Invoice $record): bool => $record->corporateCustomer?->email && $record->pdf_path && Storage::exists($record->pdf_path))
                    ->action(function (Invoice $record) {
                        try {
                            Mail::to($record->corporateCustomer->email)
                                ->send(new InvoiceMail($record));

                            $record->update([
                                'email_status' => 'sent',
                                'sent_at' => now(),
                            ]);
                            InvoiceLog::logEmail($record, 'sent', $record->corporateCustomer->email, null, $record->batch_id);

                            Notification::make()->title(__('partner.invoice.messages.email_sent'))->success()->send();
                        } catch (\Exception $e) {
                            $record->update([
                                'email_status' => 'failed',
                                'email_error' => substr($e->getMessage(), 0, 500),
                            ]);
                            InvoiceLog::logEmail($record, 'failed', $record->corporateCustomer->email, $e->getMessage(), $record->batch_id);

                            Notification::make()->title(__('partner.common.error') . ': ' . $e->getMessage())->danger()->send();
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
