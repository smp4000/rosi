<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\ShiftSettlementResource\Pages;
use App\Models\GasStation;
use App\Models\ShiftSettlement;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

/**
 * Schichtabrechnung-Resource (read-only Liste + Detailansicht).
 */
class ShiftSettlementResource extends Resource
{
    protected static ?string $model = ShiftSettlement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Schichten';

    protected static ?string $modelLabel = 'Schichtabrechnung';

    protected static ?string $pluralModelLabel = 'Schichtabrechnungen';

    protected static string|\UnitEnum|null $navigationGroup = 'Tankstelle';

    protected static ?int $navigationSort = 10;

    public static function getStatusOptions(): array
    {
        return [
            'active' => 'Offen',
            'completed' => 'Abgeschlossen',
            'cancelled' => 'Abgebrochen',
        ];
    }

    public static function getStatusColors(): array
    {
        return [
            'active' => 'warning',
            'completed' => 'success',
            'cancelled' => 'gray',
        ];
    }

    // ── Formular (nicht genutzt, aber Pflicht) ─────

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    // ── Tabelle ────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('started_at')
                    ->label('Beginn')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('ended_at')
                    ->label('Ende')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('— laufend —')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('gasStation.name')
                    ->label('Tankstelle')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Mitarbeiter')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state) => static::getStatusOptions()[$state] ?? $state)
                    ->colors(static::getStatusColors())
                    ->sortable(),

                TextColumn::make('cash_report_soll')
                    ->label('Soll')
                    ->money('EUR')
                    ->toggleable(),

                TextColumn::make('cash_remaining')
                    ->label('Ist')
                    ->money('EUR')
                    ->toggleable(),

                TextColumn::make('cash_difference')
                    ->label('Differenz')
                    ->money('EUR')
                    ->color(fn ($state) => match (true) {
                        (float) $state > 1.0 => 'warning',
                        (float) $state < -1.0 => 'danger',
                        default => 'success',
                    })
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('safe_total')
                    ->label('Tresor')
                    ->money('EUR')
                    ->toggleable(),

                TextColumn::make('returns_count')
                    ->label('Ruecknahmen')
                    ->counts('returns')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                TableGroup::make('gasStation.name')
                    ->label('Tankstelle')
                    ->collapsible(),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(static::getStatusOptions()),

                SelectFilter::make('gas_station_id')
                    ->label('Tankstelle')
                    ->relationship('gasStation', 'name'),

                SelectFilter::make('user_id')
                    ->label('Mitarbeiter')
                    ->relationship('user', 'name')
                    ->searchable(),

                Filter::make('started_at')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Von')
                            ->displayFormat('d.m.Y'),
                        DatePicker::make('until')
                            ->label('Bis')
                            ->displayFormat('d.m.Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('started_at', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $q, $date) => $q->whereDate('started_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    // ── Seiten ─────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShiftSettlements::route('/'),
            'view' => Pages\ViewShiftSettlement::route('/{record}'),
        ];
    }

    // ── Eloquent Query: Tenant-Scope ───────────────

    public static function getEloquentQuery(): Builder
    {
        $tenantId = auth()->user()?->tenant_id;

        return parent::getEloquentQuery()
            ->where('tenant_id', $tenantId);
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
