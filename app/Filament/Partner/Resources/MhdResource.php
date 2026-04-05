<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\MhdResource\Pages;
use App\Models\GasStation;
use App\Models\Mhd;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filament Resource: MHD-Kontrolle im Partner-Dashboard.
 * Zeigt alle erfassten Mindesthaltbarkeitsdaten der Stationen.
 */
class MhdResource extends Resource
{
    protected static ?string $model = Mhd::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'Bistro';

    protected static ?string $modelLabel = 'MHD-Eintrag';

    protected static ?string $pluralModelLabel = 'MHD-Kontrolle';

    protected static ?int $navigationSort = 2;

    /**
     * Nur MHD-Eintraege der eigenen Stationen anzeigen.
     */
    public static function getEloquentQuery(): Builder
    {
        $stationIds = GasStation::where('tenant_id', session('tenant_id'))
            ->pluck('id')
            ->toArray();

        return parent::getEloquentQuery()
            ->whereIn('station_id', $stationIds);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('station.name')
                    ->label('Station')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('article_description')
                    ->label('Artikel')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->limit(30),

                TextColumn::make('tms_no')
                    ->label('TMS')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('ean')
                    ->label('EAN')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('mhd_date')
                    ->label('MHD')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('remaining_days')
                    ->label('Restlaufzeit')
                    ->getStateUsing(fn (Mhd $record) => $record->remaining_days . ' Tage')
                    ->badge()
                    ->color(fn (Mhd $record) => match (true) {
                        $record->remaining_days < 0 => 'danger',
                        $record->remaining_days <= 7 => 'warning',
                        default => 'success',
                    })
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderByRaw("DATEDIFF(mhd_date, CURDATE()) {$direction}")),

                TextColumn::make('goods_disposed')
                    ->label('Status')
                    ->getStateUsing(fn (Mhd $record) => $record->goods_disposed ? 'Abgeschrieben' : 'Aktiv')
                    ->badge()
                    ->color(fn (Mhd $record) => $record->goods_disposed ? 'gray' : 'info'),

                TextColumn::make('date_recorded')
                    ->label('Erfasst am')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('supplier')
                    ->label('Lieferant')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('mhd_date', 'asc')
            ->filters([
                SelectFilter::make('station_id')
                    ->label('Station')
                    ->options(fn () => GasStation::where('tenant_id', session('tenant_id'))->pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('ampel')
                    ->label('Ampel-Status')
                    ->options([
                        'expired' => 'Abgelaufen (rot)',
                        'warning' => 'Warnung (orange)',
                        'ok' => 'OK (gruen)',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! $data['value']) {
                            return;
                        }
                        $today = Carbon::today()->toDateString();
                        match ($data['value']) {
                            'expired' => $query->where('mhd_date', '<', $today),
                            'warning' => $query->where('mhd_date', '>=', $today)
                                ->where('mhd_date', '<=', Carbon::today()->addDays(7)->toDateString()),
                            'ok' => $query->where('mhd_date', '>', Carbon::today()->addDays(7)->toDateString()),
                        };
                    }),

                TernaryFilter::make('goods_disposed')
                    ->label('Abgeschrieben')
                    ->placeholder('Alle')
                    ->trueLabel('Nur abgeschriebene')
                    ->falseLabel('Nur aktive'),
            ])
            ->actions([
                // MHD verlaengern
                Action::make('extend')
                    ->label('Verlaengern')
                    ->icon('heroicon-o-calendar')
                    ->color('info')
                    ->form([
                        DatePicker::make('new_mhd_date')
                            ->label('Neues MHD-Datum')
                            ->required()
                            ->displayFormat('d.m.Y')
                            ->default(fn (Mhd $record) => $record->mhd_date),
                    ])
                    ->action(function (Mhd $record, array $data) {
                        $newKenner = Mhd::generateKenner(
                            $record->station_id,
                            $record->tms_no,
                            $data['new_mhd_date'],
                        );

                        // Duplikat-Pruefung
                        $existing = Mhd::forStation($record->station_id)
                            ->where('kenner', $newKenner)
                            ->where('id', '!=', $record->id)
                            ->first();

                        if ($existing) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Duplikat')
                                ->body('Ein Eintrag mit diesem MHD-Datum existiert bereits.')
                                ->send();
                            return;
                        }

                        $record->update([
                            'mhd_date' => $data['new_mhd_date'],
                            'kenner' => $newKenner,
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('MHD verlaengert')
                            ->body("Neues MHD: " . Carbon::parse($data['new_mhd_date'])->format('d.m.Y'))
                            ->send();
                    })
                    ->visible(fn (Mhd $record) => ! $record->goods_disposed),

                // Abschreiben
                Action::make('dispose')
                    ->label('Abschreiben')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Artikel abschreiben?')
                    ->modalDescription(fn (Mhd $record) => "Soll \"{$record->article_description}\" (MHD: {$record->mhd_date->format('d.m.Y')}) als abgeschrieben markiert werden?")
                    ->action(function (Mhd $record) {
                        $record->update(['goods_disposed' => true]);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Abgeschrieben')
                            ->body("{$record->article_description} wurde als abgeschrieben markiert.")
                            ->send();
                    })
                    ->visible(fn (Mhd $record) => ! $record->goods_disposed),

                DeleteAction::make()
                    ->label('Loeschen'),
            ])
            ->bulkActions([
                BulkAction::make('bulk_dispose')
                    ->label('Ausgewaehlte abschreiben')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $records->each(fn (Mhd $record) => $record->update(['goods_disposed' => true]));

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title($records->count() . ' Artikel abgeschrieben')
                            ->send();
                    }),

                BulkAction::make('bulk_delete')
                    ->label('Ausgewaehlte loeschen')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (\Illuminate\Database\Eloquent\Collection $records) => $records->each->delete()),
            ])
            ->emptyStateHeading('Keine MHD-Eintraege')
            ->emptyStateDescription('Es wurden noch keine MHD-Daten ueber die POS-App erfasst.')
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->poll('30s');
    }

    /**
     * Navigation-Badge: Anzahl abgelaufener Artikel.
     */
    public static function getNavigationBadge(): ?string
    {
        $stationIds = GasStation::where('tenant_id', session('tenant_id'))
            ->pluck('id')
            ->toArray();

        $count = Mhd::whereIn('station_id', $stationIds)
            ->notDisposed()
            ->where('mhd_date', '<', Carbon::today()->toDateString())
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMhds::route('/'),
        ];
    }
}
