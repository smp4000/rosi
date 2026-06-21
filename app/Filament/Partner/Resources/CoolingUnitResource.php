<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\CoolingUnitResource\Pages;
use App\Models\CoolingUnit;
use App\Models\GasStation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Kuehlmoebel-Verwaltung: Geraete anlegen, Mobile-Alerts-Sensor (device_id)
 * zuordnen, Grenzwert-Preset waehlen, Live-Status sehen.
 */
class CoolingUnitResource extends Resource
{
    use \App\Filament\Concerns\HasCatalogPermissions;

    protected static ?string $permissionKey = 'partner.temperature';

    protected static ?string $model = CoolingUnit::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-beaker';

    protected static string|\UnitEnum|null $navigationGroup = 'Temperatur';

    protected static ?string $modelLabel = 'Kühlmöbel';

    protected static ?string $pluralModelLabel = 'Kühlmöbel';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Gerät')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Bezeichnung')
                        ->placeholder('z. B. Fisch-Theke')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('device_number')
                        ->label('Geräte-Nr. (#)')
                        ->helperText('Nummer für das HACCP-Formblatt')
                        ->numeric()
                        ->minValue(1),

                    Select::make('station_id')
                        ->label('Tankstelle')
                        ->options(fn () => GasStation::where('tenant_id', session('tenant_id'))->pluck('name', 'id'))
                        ->required()
                        ->searchable(),

                    Select::make('preset')
                        ->label('Grenzwert-Vorlage (HACCP)')
                        ->options(collect(CoolingUnit::PRESETS)->map(fn ($p) => $p[0]))
                        ->helperText('Setzt Typ + Grenzwerte automatisch (ARAL HQM 7.0).')
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            $preset = CoolingUnit::PRESETS[$state] ?? null;
                            if (! $preset) {
                                return;
                            }
                            $set('type', $preset[1]);
                            $set('target_min', $preset[2]);
                            $set('target_max', $preset[3]);
                            $set('category', $preset[0]);
                        }),

                    Select::make('type')
                        ->label('Typ')
                        ->options(CoolingUnit::TYPE_LABELS)
                        ->default('fridge')
                        ->required(),

                    TextInput::make('category')
                        ->label('Kategorie')
                        ->placeholder('z. B. Fisch, Pasta')
                        ->maxLength(255),
                ]),

            Section::make('Sensor (Mobile Alerts)')
                ->columns(2)
                ->schema([
                    TextInput::make('device_id')
                        ->label('Sensor-ID (device id)')
                        ->helperText('Aus der Mobile-Alerts-App, pro Tankstelle unterschiedlich.')
                        ->required()
                        ->maxLength(40),

                    Select::make('channel')
                        ->label('Kanal')
                        ->options(['t1' => 't1 — Luft/Sensor', 't2' => 't2 — Fühler im Möbel'])
                        ->default('t1')
                        ->required(),

                    TextInput::make('sensor_type')
                        ->label('Sensortyp')
                        ->placeholder('ID02')
                        ->default('ID02')
                        ->maxLength(10),

                    TextInput::make('phoneid')
                        ->label('phoneid (optional)')
                        ->helperText('Nur falls für dieses Gerät abweichend.')
                        ->maxLength(40),
                ]),

            Section::make('Grenzwerte & Überwachung')
                ->columns(2)
                ->schema([
                    TextInput::make('target_min')
                        ->label('Soll min (°C)')
                        ->numeric()
                        ->helperText('Leer = keine untere Grenze'),

                    TextInput::make('target_max')
                        ->label('Soll max (°C)')
                        ->numeric()
                        ->helperText('Leer = keine obere Grenze'),

                    Toggle::make('track_humidity')
                        ->label('Feuchte überwachen')
                        ->live(),

                    TextInput::make('offline_after_min')
                        ->label('Offline nach (Min)')
                        ->numeric()
                        ->default(30)
                        ->required(),

                    TextInput::make('humidity_min')
                        ->label('Feuchte min (%)')
                        ->numeric()
                        ->visible(fn (Get $get) => $get('track_humidity')),

                    TextInput::make('humidity_max')
                        ->label('Feuchte max (%)')
                        ->numeric()
                        ->visible(fn (Get $get) => $get('track_humidity')),

                    Toggle::make('is_active')
                        ->label('Aktiv')
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('device_number')->label('#')->sortable()->toggleable(),
                TextColumn::make('name')->label('Bezeichnung')->searchable()->sortable(),
                TextColumn::make('station.name')->label('Tankstelle')->sortable()->toggleable(),
                TextColumn::make('type')
                    ->label('Typ')
                    ->formatStateUsing(fn ($state) => CoolingUnit::TYPE_LABELS[$state] ?? $state)
                    ->badge(),

                TextColumn::make('last_value')
                    ->label('Aktuell')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : number_format((float) $state, 1, ',', '.') . ' °C')
                    ->color(fn (CoolingUnit $r) => match ($r->last_status) {
                        'ok' => 'success',
                        'high', 'low' => 'danger',
                        'offline' => 'gray',
                        default => 'gray',
                    })
                    ->weight('bold'),

                TextColumn::make('target_range')
                    ->label('Soll')
                    ->state(fn (CoolingUnit $r) => trim(
                        ($r->target_min !== null ? number_format((float) $r->target_min, 0) . '°' : '')
                        . ' bis '
                        . ($r->target_max !== null ? number_format((float) $r->target_max, 0) . '°' : '')
                    )),

                TextColumn::make('last_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'ok' => 'OK', 'high' => 'Zu warm', 'low' => 'Zu kalt', 'offline' => 'Offline', default => '—',
                    })
                    ->color(fn ($state) => match ($state) {
                        'ok' => 'success', 'high', 'low' => 'danger', 'offline' => 'warning', default => 'gray',
                    }),

                TextColumn::make('last_reading_at')->label('Zuletzt')->since()->placeholder('—'),
                IconColumn::make('last_lowbat')->label('Batt.')->boolean()
                    ->trueIcon('heroicon-o-battery-0')->falseIcon('heroicon-o-battery-100')
                    ->trueColor('danger')->falseColor('success')->toggleable(),
                IconColumn::make('is_active')->label('Aktiv')->boolean(),
            ])
            ->defaultSort('device_number')
            ->filters([
                SelectFilter::make('station_id')->label('Tankstelle')->relationship('station', 'name'),
            ])
            ->headerActions([
                Action::make('pollNow')
                    ->label('Jetzt abrufen')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    ->action(function () {
                        $stats = app(\App\Services\TemperaturePollService::class)->poll();
                        \Filament\Notifications\Notification::make()
                            ->title('Temperaturen abgerufen')
                            ->body("{$stats['units']} Geräte, {$stats['readings']} Messungen, {$stats['errors']} Fehler.")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoolingUnits::route('/'),
            'create' => Pages\CreateCoolingUnit::route('/create'),
            'edit' => Pages\EditCoolingUnit::route('/{record}/edit'),
        ];
    }
}
