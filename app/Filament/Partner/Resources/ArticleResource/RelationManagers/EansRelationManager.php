<?php

namespace App\Filament\Partner\Resources\ArticleResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EansRelationManager extends RelationManager
{
    protected static string $relationship = 'eans';

    protected static ?string $title = 'EAN-Codes';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-bars-3-bottom-left';

    protected static ?string $modelLabel = 'EAN';

    protected static ?string $pluralModelLabel = 'EAN-Codes';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('ean')
                ->label('EAN-Code')
                ->required()
                ->maxLength(20),
            TextInput::make('selling_price')
                ->label('VK an EAN (EUR)')
                ->numeric()
                ->step(0.01)
                ->default(0.00)
                ->prefix('EUR'),
            TextInput::make('quantity_factor')
                ->label('Mengenfaktor')
                ->maxLength(50),
            TextInput::make('base_quantity')
                ->label('Basismenge')
                ->numeric()
                ->step(0.001),
            TextInput::make('reference_quantity')
                ->label('Referenzmenge')
                ->numeric()
                ->step(0.001),
            TextInput::make('base_unit')
                ->label('Basiseinheit')
                ->maxLength(20),
            TextInput::make('sales_packaging')
                ->label('Verkaufsgebinde')
                ->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ean')
                    ->label('EAN-Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->fontFamily('mono'),

                TextColumn::make('selling_price')
                    ->label('VK')
                    ->money('EUR')
                    ->sortable(),

                TextColumn::make('quantity_factor')
                    ->label('Mengenfaktor')
                    ->toggleable(),

                TextColumn::make('base_quantity')
                    ->label('Basismenge')
                    ->toggleable(),

                TextColumn::make('reference_quantity')
                    ->label('Referenzmenge')
                    ->toggleable(),

                TextColumn::make('base_unit')
                    ->label('Einheit')
                    ->toggleable(),

                TextColumn::make('sales_packaging')
                    ->label('Verkaufsgebinde')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('ean')
            ->headerActions([
                Actions\CreateAction::make()
                    ->label('EAN hinzufügen')
                    ->mutateFormDataUsing(function (array $data): array {
                        $owner = $this->getOwnerRecord();
                        $data['gas_station_id'] = $owner->gas_station_id;
                        $data['article_number'] = $owner->article_number;
                        $data['imported_at'] = now();
                        return $data;
                    }),
            ])
            ->actions([
                Actions\EditAction::make()->label('Bearbeiten'),
                Actions\DeleteAction::make()->label('Löschen'),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
