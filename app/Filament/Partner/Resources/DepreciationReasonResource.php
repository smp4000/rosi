<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\DepreciationReasonResource\Pages;
use App\Models\DepreciationReason;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Filament Resource: Abschreibungsgruende im Partner-Dashboard.
 * Zeigt Standard-Gruende (fuer alle) + eigene Gruende des Tenants.
 */
class DepreciationReasonResource extends Resource
{
    protected static ?string $model = DepreciationReason::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Bistro / MHD';

    protected static ?string $modelLabel = 'Abschreibungsgrund';

    protected static ?string $pluralModelLabel = 'Abschreibungsgründe';

    protected static ?int $navigationSort = 10;

    /**
     * Nur Standard-Gruende + eigene Gruende des Tenants anzeigen.
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
                TextColumn::make('name')
                    ->label('Bezeichnung')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Beschreibung')
                    ->placeholder('–')
                    ->limit(50),

                IconColumn::make('status')
                    ->label('Aktiv')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_default')
                    ->label('Standard')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-user')
                    ->trueColor('gray')
                    ->falseColor('primary'),

                TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('status')
                    ->label('Status')
                    ->trueLabel('Nur aktive')
                    ->falseLabel('Nur inaktive')
                    ->placeholder('Alle'),

                TernaryFilter::make('is_default')
                    ->label('Herkunft')
                    ->trueLabel('Nur Standard')
                    ->falseLabel('Nur eigene')
                    ->placeholder('Alle'),
            ])
            ->actions([
                EditAction::make()
                    ->form([
                        TextInput::make('name')
                            ->label('Bezeichnung')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Beschreibung')
                            ->maxLength(500),
                        Toggle::make('status')
                            ->label('Aktiv'),
                    ])
                    ->visible(fn (DepreciationReason $record) => ! $record->is_default),

                // Standard-Gruende: nur Status aendern (aktivieren/deaktivieren)
                Action::make('toggleStatus')
                    ->label(fn (DepreciationReason $record) => $record->status ? 'Deaktivieren' : 'Aktivieren')
                    ->icon(fn (DepreciationReason $record) => $record->status ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (DepreciationReason $record) => $record->status ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (DepreciationReason $record) => $record->update(['status' => ! $record->status]))
                    ->visible(fn (DepreciationReason $record) => $record->is_default),

                DeleteAction::make()
                    ->visible(fn (DepreciationReason $record) => ! $record->is_default),
            ])
            ->bulkActions([
                BulkAction::make('activate')
                    ->label('Aktivieren')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->update(['status' => true])),

                BulkAction::make('deactivate')
                    ->label('Deaktivieren')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->update(['status' => false])),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Neuer Grund')
                    ->form([
                        TextInput::make('name')
                            ->label('Bezeichnung')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Beschreibung')
                            ->maxLength(500),
                        Toggle::make('status')
                            ->label('Aktiv')
                            ->default(true),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = session('tenant_id');
                        $data['is_default'] = false;
                        return $data;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepreciationReasons::route('/'),
        ];
    }
}
