<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\InvoiceBatchResource\Pages;
use App\Models\GasStation;
use App\Models\InvoiceBatch;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Import-Batch Resource fuer Rechnungs-Imports.
 * Zeigt Uebersicht aller Batches mit Statistik.
 */
class InvoiceBatchResource extends Resource
{
    protected static ?string $model = InvoiceBatch::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Rechnungen';

    protected static ?string $navigationLabel = 'Import-Verlauf';

    protected static ?string $modelLabel = 'Import-Batch';

    protected static ?string $pluralModelLabel = 'Import-Batches';

    protected static ?int $navigationSort = 22;

    public static function getEloquentQuery(): Builder
    {
        $tenantId = auth()->user()?->tenant_id;

        return parent::getEloquentQuery()
            ->whereHas('gasStation', fn (Builder $q) => $q->where('tenant_id', $tenantId));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Batch-Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('gasStation.name')
                    ->label('Tankstelle')
                    ->sortable(),

                TextColumn::make('total_files')
                    ->label('Gesamt')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('success_count')
                    ->label('Erfolgreich')
                    ->sortable()
                    ->alignCenter()
                    ->color('success'),

                TextColumn::make('error_count')
                    ->label('Fehler')
                    ->sortable()
                    ->alignCenter()
                    ->color('danger')
                    ->formatStateUsing(fn ($state) => (int) $state > 0 ? $state : '-'),

                TextColumn::make('duplicate_count')
                    ->label('Duplikate')
                    ->sortable()
                    ->alignCenter()
                    ->color('warning')
                    ->formatStateUsing(fn ($state) => (int) $state > 0 ? $state : '-'),

                TextColumn::make('new_customers_count')
                    ->label('Neue Kunden')
                    ->sortable()
                    ->alignCenter()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => (int) $state > 0 ? $state : '-'),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Ausstehend',
                        'processing' => 'Laeuft',
                        'completed' => 'Abgeschlossen',
                        'failed' => 'Fehlgeschlagen',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'processing' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Erstellt am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Ausstehend',
                        'processing' => 'Laeuft',
                        'completed' => 'Abgeschlossen',
                        'failed' => 'Fehlgeschlagen',
                    ]),
            ])
            ->actions([
                Actions\Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->url(fn (InvoiceBatch $record) => static::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoiceBatches::route('/'),
            'view' => Pages\ViewInvoiceBatch::route('/{record}'),
        ];
    }
}
