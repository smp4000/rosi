<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppVersionResource\Pages;
use App\Models\AppVersion;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Filament Resource: App-Versionshistorie verwalten (nur Admin-Panel).
 */
class AppVersionResource extends Resource
{
    protected static ?string $model = AppVersion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'POS App';

    protected static ?string $modelLabel = 'App-Version';

    protected static ?string $pluralModelLabel = 'App-Versionen';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('version')
                    ->label('Version')
                    ->placeholder('z.B. 1.1.0')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),

                DatePicker::make('release_date')
                    ->label('Veroeffentlichungsdatum')
                    ->required()
                    ->default(now())
                    ->displayFormat('d.m.Y'),

                Toggle::make('is_published')
                    ->label('Veroeffentlicht')
                    ->helperText('Nur veroeffentlichte Versionen werden in der App angezeigt.')
                    ->default(true),

                Repeater::make('changes')
                    ->label('Aenderungen')
                    ->simple(
                        TextInput::make('change')
                            ->placeholder('Beschreibung der Aenderung...')
                            ->required(),
                    )
                    ->addActionLabel('Aenderung hinzufuegen')
                    ->reorderable()
                    ->collapsible()
                    ->defaultItems(1)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version')
                    ->label('Version')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('release_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('changes')
                    ->label('Aenderungen')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) {
                            return count($state) . ' Aenderung(en)';
                        }
                        return '—';
                    }),

                BooleanColumn::make('is_published')
                    ->label('Veroeffentlicht'),

                TextColumn::make('created_at')
                    ->label('Erstellt am')
                    ->date('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('release_date', 'desc')
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Noch keine Versionen erfasst')
            ->emptyStateDescription('Erstellen Sie den ersten Versionseintrag fuer die POS-App.')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppVersions::route('/'),
            'create' => Pages\CreateAppVersion::route('/create'),
            'edit' => Pages\EditAppVersion::route('/{record}/edit'),
        ];
    }
}
