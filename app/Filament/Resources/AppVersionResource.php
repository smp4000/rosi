<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppVersionResource\Pages;
use App\Models\AppVersion;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
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
                        'print-agent' => 'Druck-Agent (Windows)',
                    ])
                    ->required()
                    ->live()
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

                // ── In-App-Updater (nur fuer die POS-App) ──
                TextInput::make('version_code')
                    ->label('Version-Code (technisch)')
                    ->helperText(fn (string $operation) => $operation === 'create'
                        ? 'Vorbelegt mit dem naechsten freien Code. Muss hoeher sein als der letzte ('
                            . (static::nextVersionCode() - 1) . '). Wird beim Speichern automatisch aus der APK uebernommen.'
                        : 'Muss dem versionCode der APK entsprechen. Wird beim Speichern automatisch aus der APK uebernommen.')
                    ->numeric()
                    ->default(fn (Get $get) => $get('platform') === 'print-agent' ? 1 : static::nextVersionCode())
                    // Mindestwert 1: print-agent zaehlt eigenstaendig (1,2,3…), bei der
                    // App wird der echte version_code beim Speichern aus der APK uebernommen.
                    ->minValue(1)
                    ->helperText(fn (Get $get) => $get('platform') === 'print-agent'
                        ? 'Fortlaufende Nummer des Agents (1, 2, 3 …). Muss hoeher sein als die installierte.'
                        : 'Wird beim Speichern automatisch aus der APK uebernommen.')
                    ->visible(fn (Get $get) => in_array($get('platform'), ['app', 'print-agent'], true))
                    ->requiredWith('apk'),

                FileUpload::make('apk_path')
                    ->label(fn (Get $get) => $get('platform') === 'print-agent' ? 'Agent-EXE (RosiPrintAgent.exe)' : 'APK-Datei')
                    ->helperText(fn (Get $get) => $get('platform') === 'print-agent'
                        ? 'Die RosiPrintAgent.exe. Der Agent laedt genau diese Datei und ersetzt sich selbst.'
                        : 'Die signierte Release-APK. Die App laedt genau diese Datei herunter und installiert sie.')
                    ->disk('public')
                    ->directory(fn (Get $get) => $get('platform') === 'print-agent' ? 'agents' : 'apks')
                    ->visibility('public')
                    // Kein MIME-Filter: APKs werden je nach Server als
                    // application/zip / java-archive / octet-stream erkannt
                    // (eine APK ist technisch ein ZIP) — fuehrte zu
                    // "validation.mimetypes".
                    ->preserveFilenames()
                    ->maxSize(204800) // 200 MB
                    ->downloadable()
                    ->visible(fn (Get $get) => in_array($get('platform'), ['app', 'print-agent'], true))
                    // Datei-Groesse mitschreiben
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state instanceof \Illuminate\Http\UploadedFile) {
                            $set('apk_size', $state->getSize());
                        }
                    }),

                TextInput::make('apk_size')
                    ->hidden()
                    ->dehydrated(),

                Toggle::make('is_mandatory')
                    ->label('Pflicht-Update')
                    ->helperText('Wenn aktiv, MUSS der Nutzer aktualisieren (kein "Spaeter"-Button).')
                    ->default(false)
                    ->visible(fn (Get $get) => in_array($get('platform'), ['app', 'print-agent'], true)),

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
                        'print-agent' => 'Druck-Agent',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'app' => 'success',
                        'web' => 'info',
                        'print-agent' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('version')
                    ->label('Version')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->description(fn (AppVersion $record) => $record->version_code ? "Code {$record->version_code}" : null),

                \Filament\Tables\Columns\IconColumn::make('apk_path')
                    ->label('APK')
                    ->boolean()
                    ->state(fn (AppVersion $record) => ! empty($record->apk_path))
                    ->tooltip(fn (AppVersion $record) => $record->is_mandatory ? 'APK vorhanden · Pflicht-Update' : ($record->apk_path ? 'APK vorhanden' : 'Keine APK')),

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
                        'print-agent' => 'Druck-Agent',
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

    /** Naechster freier version_code = hoechster vorhandener + 1. */
    public static function nextVersionCode(): int
    {
        return (int) (AppVersion::max('version_code') ?? 0) + 1;
    }
}
