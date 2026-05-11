<?php

namespace App\Filament\Partner\Resources\VoucherResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Zeigt die Einloesungs-Historie eines Gutscheins.
 */
class RedemptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'redemptions';

    protected static ?string $title = 'Einloesungen';

    protected static ?string $modelLabel = 'Einloesung';

    protected static ?string $pluralModelLabel = 'Einloesungen';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('redeemed_at')
                    ->label('Datum')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Betrag')
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_after')
                    ->label('Restguthaben danach')
                    ->money('EUR'),

                Tables\Columns\TextColumn::make('station.name')
                    ->label('Station')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('employee_name')
                    ->label('Mitarbeiter'),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notizen')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('redeemed_at', 'desc');
    }
}
