<?php

namespace App\Filament\Partner\Resources\NewspaperSupplierResource\RelationManagers;

use App\Models\GasStation;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StationsRelationManager extends RelationManager
{
    protected static string $relationship = 'stations';

    protected static ?string $title = 'Tankstellen mit Kundennummer';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('recordId')
                ->label('Tankstelle')
                ->options(function () {
                    return GasStation::where('tenant_id', auth()->user()->tenant_id)
                        ->orderBy('name')
                        ->pluck('name', 'id');
                })
                ->required()
                ->searchable()
                ->preload(),

            TextInput::make('kundennummer')
                ->label('Kundennummer')
                ->required()
                ->maxLength(50)
                ->helperText('Die beim Lieferanten hinterlegte Kunden-Nr fuer diese Tankstelle.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Tankstelle')->searchable(),
                TextColumn::make('pivot.kundennummer')->label('Kundennummer')->fontFamily('mono')->weight('bold'),
                TextColumn::make('pivot.created_at')->label('Hinzugefuegt')->dateTime('d.m.Y')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->where('tenant_id', auth()->user()->tenant_id))
                    ->form(fn (Actions\AttachAction $action) => [
                        $action->getRecordSelect()->label('Tankstelle')->required(),
                        TextInput::make('kundennummer')->label('Kundennummer')->required()->maxLength(50),
                    ]),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->form([
                        TextInput::make('kundennummer')->label('Kundennummer')->required()->maxLength(50),
                    ]),
                Actions\DetachAction::make(),
            ])
            ->bulkActions([]);
    }
}
