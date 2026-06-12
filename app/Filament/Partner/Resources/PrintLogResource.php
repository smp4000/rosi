<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\PrintLogResource\Pages;
use App\Models\GasStation;
use App\Models\InvoiceLog;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Druck-Protokoll.
 * Zeigt alle als gedruckt markierten Rechnungen.
 * Read-only.
 */
class PrintLogResource extends Resource
{
    use \App\Filament\Concerns\HasCatalogPermissions;

    /** Katalog-Schluessel fuer die Rechte-Pruefung (Rollen-Matrix) */
    protected static ?string $permissionKey = 'partner.print';

    protected static ?string $model = InvoiceLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-printer';

    protected static string|\UnitEnum|null $navigationGroup = 'Rechnungen';

    protected static ?string $navigationLabel = 'Druck-Protokoll';

    protected static ?string $modelLabel = 'Druck-Eintrag';

    protected static ?string $pluralModelLabel = 'Druck-Protokoll';

    protected static ?int $navigationSort = 26;

    protected static ?string $slug = 'print-logs';

    public static function getNavigationLabel(): string
    {
        return __('partner.print_log.plural');
    }

    public static function getModelLabel(): string
    {
        return __('partner.print_log.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('partner.print_log.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('partner.invoice.nav_group');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('channel', 'print')
            ->with(['invoice.corporateCustomer', 'invoice.gasStation']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('partner.invoice.fields.datum'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('invoice.invoice_number')
                    ->label(__('partner.invoice.fields.invoice_number'))
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                TextColumn::make('invoice.corporateCustomer.name')
                    ->label(__('partner.invoice.fields.customer'))
                    ->searchable()
                    ->limit(25),

                TextColumn::make('invoice.corporateCustomer.full_address')
                    ->label(__('partner.corporate_customer.fields.address'))
                    ->limit(35),

                TextColumn::make('invoice.gasStation.name')
                    ->label(__('partner.invoice.fields.gas_station'))
                    ->limit(20),

                TextColumn::make('invoice.invoice_date')
                    ->label(__('partner.invoice.fields.invoice_date'))
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('invoice.amount')
                    ->label(__('partner.invoice.fields.betrag'))
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('partner.invoice.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (): string => __('partner.invoice.print_statuses.printed'))
                    ->color('success'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('gas_station')
                    ->label(__('partner.invoice.fields.gas_station'))
                    ->options(function () {
                        $tenantId = auth()->user()?->tenant_id;

                        return $tenantId
                            ? GasStation::where('tenant_id', $tenantId)->pluck('name', 'id')
                            : [];
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value']) {
                            return $query->whereHas('invoice', fn ($q) => $q->where('gas_station_id', $data['value']));
                        }

                        return $query;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrintLogs::route('/'),
        ];
    }
}
