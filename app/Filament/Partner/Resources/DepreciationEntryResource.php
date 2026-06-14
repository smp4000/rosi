<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\DepreciationEntryResource\Pages;
use App\Models\DepreciationEntry;
use App\Models\DepreciationReason;
use App\Models\GasStation;
use Filament\Resources\Resource;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filament Resource: Abschriften (Warenverluste) im Partner-Dashboard.
 * Datenquelle ist die POS-App (depreciation_entries). Read-only Liste mit
 * Filtern + Tagesbericht-Erstellung (PDF) ueber die ListPage-Header-Action.
 */
class DepreciationEntryResource extends Resource
{
    use \App\Filament\Concerns\HasCatalogPermissions;

    protected static ?string $permissionKey = 'partner.depreciations';

    protected static ?string $model = DepreciationEntry::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-trending-down';

    protected static string|\UnitEnum|null $navigationGroup = 'Bistro';

    protected static ?string $modelLabel = 'Abschrift';

    protected static ?string $pluralModelLabel = 'Abschriften';

    protected static ?int $navigationSort = 3;

    /** Nur Abschriften der eigenen Stationen. */
    public static function getEloquentQuery(): Builder
    {
        $stationIds = GasStation::where('tenant_id', session('tenant_id'))
            ->pluck('id')
            ->toArray();

        return parent::getEloquentQuery()->whereIn('station_id', $stationIds);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recorded_at')
                    ->label('Erfasst am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('station.name')
                    ->label('Station')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('article_description')
                    ->label('Artikel')
                    ->weight('bold')
                    ->searchable()
                    ->limit(35),

                TextColumn::make('tms_no')
                    ->label('TMS')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ean')
                    ->label('EAN')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('quantity')
                    ->label('Menge')
                    ->alignEnd()
                    ->sortable(),

                TextColumn::make('reason.name')
                    ->label('Grund')
                    ->badge()
                    ->color('warning'),

                TextColumn::make('purchasing_price')
                    ->label('EK')
                    ->money('EUR')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('selling_price')
                    ->label('VK')
                    ->money('EUR')
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_purchasing')
                    ->label('Gesamt-EK')
                    ->getStateUsing(fn (DepreciationEntry $r) => $r->total_purchasing)
                    ->money('EUR')
                    ->alignEnd(),

                TextColumn::make('total_selling')
                    ->label('Gesamt-VK')
                    ->getStateUsing(fn (DepreciationEntry $r) => $r->total_selling)
                    ->money('EUR')
                    ->alignEnd(),

                TextColumn::make('source')
                    ->label('Quelle')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'batch' => 'Sammel',
                        'single' => 'Einzel',
                        'mhd' => 'MHD',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'mhd' => 'danger',
                        'batch' => 'info',
                        default => 'gray',
                    })
                    ->toggleable(),

                TextColumn::make('user.name')
                    ->label('Erfasst von')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->filters([
                SelectFilter::make('station_id')
                    ->label('Station')
                    ->options(fn () => GasStation::where('tenant_id', session('tenant_id'))->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('depreciation_reason_id')
                    ->label('Grund')
                    ->options(fn () => DepreciationReason::query()
                        ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', session('tenant_id')))
                        ->pluck('name', 'id')),

                SelectFilter::make('source')
                    ->label('Quelle')
                    ->options(['single' => 'Einzel', 'batch' => 'Sammel', 'mhd' => 'MHD']),

                Filter::make('recorded_at')
                    ->schema([
                        DatePicker::make('from')->label('Von')->displayFormat('d.m.Y'),
                        DatePicker::make('until')->label('Bis')->displayFormat('d.m.Y'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('recorded_at', '>=', $d))
                            ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('recorded_at', '<=', $d));
                    }),
            ])
            ->actions([
                DeleteAction::make()->label('Loeschen'),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('Keine Abschriften')
            ->emptyStateDescription('Es wurden noch keine Abschriften ueber die POS-App erfasst.')
            ->emptyStateIcon('heroicon-o-arrow-trending-down')
            ->poll('60s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepreciationEntries::route('/'),
        ];
    }
}
