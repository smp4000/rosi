<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShiftSettlementCheckQuestionResource\Pages;
use App\Models\ShiftSettlementCheckQuestion;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament Resource fuer Schicht-Prueffragen.
 * Hier koennen Prueffragen konfiguriert werden, die Mitarbeiter
 * beim Schichtbeginn in der POS-App beantworten muessen.
 */
class ShiftSettlementCheckQuestionResource extends Resource
{
    protected static ?string $model = ShiftSettlementCheckQuestion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Schichtabrechnung';

    protected static ?string $modelLabel = 'Prueffrage';

    protected static ?string $pluralModelLabel = 'Schicht-Prueffragen';

    protected static ?int $navigationSort = 2;

    // --- Autorisierung ---

    public static function canAccess(): bool
    {
        return auth()->user()->isSuperAdmin() || auth()->user()->can('admin.shift-settlements.manage');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->isSuperAdmin() || auth()->user()->can('admin.shift-settlements.manage');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->isSuperAdmin() || auth()->user()->can('admin.shift-settlements.manage');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->isSuperAdmin() || auth()->user()->can('admin.shift-settlements.manage');
    }

    // --- Formular ---

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('question_text')
                ->label('Fragetext')
                ->required()
                ->maxLength(255)
                ->placeholder('z.B. Haben Sie Waren ohne Kassenbon angetroffen?')
                ->columnSpanFull(),

            Select::make('question_type')
                ->label('Fragetyp')
                ->options([
                    'yes_no' => 'Ja / Nein',
                    'yes_no_comment' => 'Ja / Nein + Pflichtkommentar bei Nein',
                    'text' => 'Freitext',
                    'number' => 'Zahl',
                ])
                ->default('yes_no')
                ->required()
                ->helperText('Bei "Pflichtkommentar bei Nein" muss der Mitarbeiter einen Kommentar eingeben wenn er "Nein" waehlt.'),

            Select::make('gas_station_id')
                ->label('Station')
                ->relationship('gasStation', 'name')
                ->searchable()
                ->preload()
                ->placeholder('Alle Stationen')
                ->helperText('Leer lassen = gilt fuer alle Stationen.'),

            TextInput::make('sort_order')
                ->label('Reihenfolge')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->helperText('Niedrigere Zahl = weiter oben.'),

            Toggle::make('is_active')
                ->label('Aktiv')
                ->default(true)
                ->helperText('Inaktive Fragen werden in der App nicht angezeigt.'),
        ]);
    }

    // --- Tabelle ---

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width('60px'),

                TextColumn::make('question_text')
                    ->label('Frage')
                    ->searchable()
                    ->sortable()
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->question_text)
                    ->weight('bold'),

                TextColumn::make('question_type')
                    ->label('Typ')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'yes_no' => 'Ja / Nein',
                        'yes_no_comment' => 'Ja / Nein + Kommentar',
                        'text' => 'Freitext',
                        'number' => 'Zahl',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'yes_no' => 'info',
                        'yes_no_comment' => 'warning',
                        'text' => 'gray',
                        'number' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('gasStation.name')
                    ->label('Station')
                    ->placeholder('Alle Stationen')
                    ->sortable(),

                BooleanColumn::make('is_active')
                    ->label('Aktiv')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Nur aktive')
                    ->falseLabel('Nur inaktive'),

                SelectFilter::make('question_type')
                    ->label('Fragetyp')
                    ->options([
                        'yes_no' => 'Ja / Nein',
                        'yes_no_comment' => 'Ja / Nein + Kommentar',
                        'text' => 'Freitext',
                        'number' => 'Zahl',
                    ]),

                SelectFilter::make('gas_station_id')
                    ->label('Station')
                    ->relationship('gasStation', 'name'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // --- Seiten ---

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShiftSettlementCheckQuestions::route('/'),
            'create' => Pages\CreateShiftSettlementCheckQuestion::route('/create'),
            'edit' => Pages\EditShiftSettlementCheckQuestion::route('/{record}/edit'),
        ];
    }
}
