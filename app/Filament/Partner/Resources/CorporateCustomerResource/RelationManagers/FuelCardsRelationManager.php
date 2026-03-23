<?php

namespace App\Filament\Partner\Resources\CorporateCustomerResource\RelationManagers;

use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Tankkarten-Verwaltung im Firmenkunden-Kontext.
 * Zeigt alle Tankkarten eines Firmenkunden.
 */
class FuelCardsRelationManager extends RelationManager
{
    protected static string $relationship = 'fuelCards';

    protected static ?string $title = 'Tankkarten';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-credit-card';

    protected static ?string $modelLabel = 'Tankkarte';

    protected static ?string $pluralModelLabel = 'Tankkarten';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('card_number')
                ->label('Teilnehmer-Nr.')
                ->required()
                ->numeric(),

            TextInput::make('driver_name')
                ->label('Fahrer')
                ->maxLength(255),

            TextInput::make('license_plate')
                ->label('Kennzeichen')
                ->maxLength(50),

            TextInput::make('notes')
                ->label('Zusatzvermerke')
                ->maxLength(500)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                 TextColumn::make('gasStation.name')
                    ->label('Tankstelle')
                    ->description(fn ($record) => $record->gasStation?->station_number_shop ?? $record->gasStation?->station_number ?? '')
                    ->sortable()
                    ->toggleable(),  

                TextColumn::make('card_number')
                    ->label('Karten-Nr.')
                    ->sortable()
                    ->searchable()
                    ->fontFamily('mono'),

                TextColumn::make('driver_name')
                    ->label('Fahrer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('license_plate')
                    ->label('Kennzeichen')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('notes')
                    ->label('Zusatzvermerke')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->defaultSort('card_number', 'asc')
            ->headerActions([
                Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $owner = $this->getOwnerRecord();
                        $data['gas_station_id'] = $owner->gas_station_id;

                        return $data;
                    }),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }
}
