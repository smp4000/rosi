<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\ShiftReturnReasonResource\Pages;
use App\Models\ShiftReturnReason;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filament Resource: Warenruecknahme-Gruende im Partner-Dashboard.
 * Zeigt globale Gruende (tenant_id=null) + eigene Gruende des Tenants.
 */
class ShiftReturnReasonResource extends Resource
{
    protected static ?string $model = ShiftReturnReason::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Einstellungen';

    protected static ?string $modelLabel = 'Rücknahme-Grund';

    protected static ?string $pluralModelLabel = 'Rücknahme-Gründe';

    protected static ?int $navigationSort = 11;

    /**
     * Globale Gruende + eigene Gruende des Tenants anzeigen.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->forTenant(session('tenant_id'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Bezeichnung')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('Reihenfolge')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Aktiv')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_global')
                    ->label('Global')
                    ->state(fn (ShiftReturnReason $record) => $record->tenant_id === null)
                    ->boolean()
                    ->trueIcon('heroicon-o-globe-alt')
                    ->falseIcon('heroicon-o-user')
                    ->trueColor('gray')
                    ->falseColor('primary'),

                TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->actions([
                EditAction::make()
                    ->form([
                        TextInput::make('label')
                            ->label('Bezeichnung')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('sort_order')
                            ->label('Reihenfolge')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->label('Aktiv'),
                    ])
                    ->visible(fn (ShiftReturnReason $record) => $record->tenant_id !== null),

                DeleteAction::make()
                    ->visible(fn (ShiftReturnReason $record) => $record->tenant_id !== null),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Neuer Grund')
                    ->form([
                        TextInput::make('label')
                            ->label('Bezeichnung')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('sort_order')
                            ->label('Reihenfolge')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->label('Aktiv')
                            ->default(true),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = session('tenant_id');
                        return $data;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShiftReturnReasons::route('/'),
        ];
    }
}
