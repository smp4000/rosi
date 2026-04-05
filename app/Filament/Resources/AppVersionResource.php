<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppVersionResource\Pages;
use App\Models\AppVersion;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Filament Resource: App-Versionshistorie verwalten (nur Admin-Panel).
 */
class AppVersionResource extends Resource
{
    protected static ?string $model = AppVersion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'POS App';

    protected static ?string $modelLabel = 'Version';

    protected static ?string $pluralModelLabel = 'Versionshistorie';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('platform')
                    ->label('Plattform')
                    ->options([
                        'app' => 'POS-App (Android)',
                        'web' => 'Web-Dashboard',
                    ])
                    ->required()
                    ->default('app'),

                TextInput::make('version')
                    ->label('Version')
                    ->placeholder('z.B. 1.5.0')
                    ->required()
                    ->maxLength(20)
                    ->unique(
                        table: 'app_versions',
                        column: 'version',
                        ignoreRecord: true,
                        modifyRuleUsing: fn ($rule, $get) => $rule->where('platform', $get('platform')),
                    ),

                DatePicker::make('release_date')
                    ->label('Veroeffentlichungsdatum')
                    ->required()
                    ->default(now())
                    ->displayFormat('d.m.Y'),

                Toggle::make('is_published')
                    ->label('Veroeffentlicht')
                    ->helperText('Nur veroeffentlichte Versionen werden angezeigt.')
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
                TextColumn::make('platform')
                    ->label('Plattform')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'app' => 'App',
                        'web' => 'Web',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'app' => 'success',
                        'web' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('version')
                    ->label('Version')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('release_date')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),

                TextColumn::make('changes')
                    ->label('Aenderungen')
                    ->getStateUsing(function (AppVersion $record) {
                        $changes = $record->changes;
                        if (is_array($changes)) {
                            return count($changes) . ' Aenderung(en)';
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
            ->filters([
                SelectFilter::make('platform')
                    ->label('Plattform')
                    ->options([
                        'app' => 'POS-App',
                        'web' => 'Web-Dashboard',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Noch keine Versionen erfasst')
            ->emptyStateDescription('Erstellen Sie den ersten Versionseintrag.')
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
