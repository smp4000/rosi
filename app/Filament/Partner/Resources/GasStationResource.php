<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\GasStationResource\Pages;
use App\Models\GasStation;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Actions;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament Resource fuer die Tankstellen-Verwaltung im Partner-Panel.
 * Daten werden automatisch auf den Mandanten beschraenkt (BelongsToTenant).
 */
class GasStationResource extends Resource
{
    protected static ?string $model = GasStation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|\UnitEnum|null $navigationGroup = 'Tankstellen';

    protected static ?string $modelLabel = 'Tankstelle';

    protected static ?string $pluralModelLabel = 'Tankstellen';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    // --- Autorisierung ---

    /**
     * Team-Kontext fuer Spatie setzen (noetig fuer Livewire-Requests
     * die nicht durch Panel-Middleware laufen).
     */
    protected static function ensureTeamContext(): void
    {
        $user = auth()->user();
        if ($user?->tenant_id) {
            $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
            $registrar->setPermissionsTeamId($user->tenant_id);
        }
    }

    public static function canAccess(): bool
    {
        static::ensureTeamContext();
        return auth()->user()->can('partner.gas-stations.list');
    }

    public static function canCreate(): bool
    {
        static::ensureTeamContext();
        return auth()->user()->can('partner.gas-stations.create');
    }

    public static function canEdit(Model $record): bool
    {
        static::ensureTeamContext();
        return auth()->user()->can('partner.gas-stations.edit');
    }

    public static function canDelete(Model $record): bool
    {
        static::ensureTeamContext();
        return auth()->user()->can('partner.gas-stations.delete');
    }

    public static function canView(Model $record): bool
    {
        static::ensureTeamContext();
        return auth()->user()->can('partner.gas-stations.view');
    }

    // --- Formular (Tabs) ---

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Tankstelle')
                ->tabs([
                    Tab::make(__('partner.gas_station.tabs.stammdaten'))
                        ->icon('heroicon-o-building-storefront')
                        ->schema([
                            TextInput::make('name')
                                ->label(__('partner.gas_station.fields.name'))
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(2),
                            Select::make('brand_id')
                                ->label(__('partner.gas_station.fields.brand'))
                                ->relationship('brand', 'name')
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->label('Markenname')
                                        ->required()
                                        ->maxLength(255),
                                ])
                                ->createOptionUsing(function (array $data): int {
                                    return \App\Models\Brand::create($data)->getKey();
                                }),
                            TextInput::make('station_number')
                                ->label(__('partner.gas_station.fields.station_number'))
                                ->maxLength(50),
                            TextInput::make('tax_id')
                                ->label(__('partner.gas_station.fields.tax_id'))
                                ->maxLength(50),
                            TextInput::make('trade_register')
                                ->label(__('partner.gas_station.fields.trade_register'))
                                ->maxLength(100),
                            Toggle::make('is_active')
                                ->label(__('partner.gas_station.fields.is_active'))
                                ->default(true)
                                ->columnSpan(2),
                        ])
                        ->columns(2),

                    Tab::make(__('partner.gas_station.tabs.adresse'))
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            TextInput::make('street')
                                ->label(__('partner.gas_station.fields.street'))
                                ->maxLength(255)
                                ->columnSpan(2),
                            TextInput::make('zip')
                                ->label(__('partner.gas_station.fields.zip'))
                                ->maxLength(10),
                            TextInput::make('city')
                                ->label(__('partner.gas_station.fields.city'))
                                ->maxLength(255),
                            TextInput::make('state')
                                ->label(__('partner.gas_station.fields.state'))
                                ->maxLength(100),
                            TextInput::make('country')
                                ->label(__('partner.gas_station.fields.country'))
                                ->default('DE')
                                ->maxLength(2),
                            TextInput::make('latitude')
                                ->label(__('partner.gas_station.fields.latitude'))
                                ->numeric()
                                ->step(0.00000001),
                            TextInput::make('longitude')
                                ->label(__('partner.gas_station.fields.longitude'))
                                ->numeric()
                                ->step(0.00000001),
                        ])
                        ->columns(2),

                    Tab::make(__('partner.gas_station.tabs.kontakt'))
                        ->icon('heroicon-o-phone')
                        ->schema([
                            TextInput::make('phone')
                                ->label(__('partner.gas_station.fields.phone'))
                                ->tel()
                                ->maxLength(50),
                            TextInput::make('fax')
                                ->label(__('partner.gas_station.fields.fax'))
                                ->tel()
                                ->maxLength(50),
                            TextInput::make('email')
                                ->label(__('partner.gas_station.fields.email'))
                                ->email()
                                ->maxLength(255),
                        ])
                        ->columns(2),

                    Tab::make(__('partner.gas_station.tabs.betrieb'))
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->schema([
                            TextInput::make('num_pumps')
                                ->label(__('partner.gas_station.fields.num_pumps'))
                                ->numeric()
                                ->minValue(0),
                            Toggle::make('has_shop')
                                ->label(__('partner.gas_station.fields.has_shop')),
                            Toggle::make('has_car_wash')
                                ->label(__('partner.gas_station.fields.has_car_wash')),
                            // Verfuegbare Services als Mehrfachauswahl
                            CheckboxList::make('services')
                                ->label(__('partner.gas_station.fields.services'))
                                ->options([
                                    'super'       => 'Super (E5)',
                                    'e10'         => 'E10',
                                    'diesel'      => 'Diesel',
                                    'premium'     => 'Super Plus',
                                    'lpg'         => 'LPG / Autogas',
                                    'adblue'      => 'AdBlue',
                                    'cng'         => 'Erdgas (CNG)',
                                    'h2'          => 'Wasserstoff (H₂)',
                                    'ev'          => 'Elektro-Laden',
                                    'tire'        => 'Reifenservice',
                                    'oil'         => 'Oelwechsel',
                                    'bakery'      => 'Backshop',
                                    'cafe'        => 'Café / Restaurant',
                                    'atm'         => 'Geldautomat',
                                    'car_rental'  => 'Autovermietung',
                                ])
                                ->columns(3)
                                ->columnSpanFull(),
                            // Oeffnungszeiten pro Wochentag
                            Repeater::make('opening_hours')
                                ->label(__('partner.gas_station.fields.opening_hours'))
                                ->schema([
                                    Select::make('day')
                                        ->label('Tag')
                                        ->options([
                                            'monday'    => 'Montag',
                                            'tuesday'   => 'Dienstag',
                                            'wednesday' => 'Mittwoch',
                                            'thursday'  => 'Donnerstag',
                                            'friday'    => 'Freitag',
                                            'saturday'  => 'Samstag',
                                            'sunday'    => 'Sonntag',
                                        ])
                                        ->required()
                                        ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                    TextInput::make('open')
                                        ->label('Oeffnung')
                                        ->type('time'),
                                    TextInput::make('close')
                                        ->label('Schliessung')
                                        ->type('time'),
                                    Toggle::make('closed')
                                        ->label('Geschlossen')
                                        ->reactive(),
                                ])
                                ->columns(4)
                                ->defaultItems(0)
                                ->addActionLabel('Tag hinzufuegen')
                                ->orderColumn(false)
                                ->columnSpanFull(),
                            Textarea::make('notes')
                                ->label(__('partner.gas_station.fields.notes'))
                                ->rows(4)
                                ->maxLength(2000)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Tab::make(__('partner.gas_station.tabs.fotos'))
                        ->icon('heroicon-o-photo')
                        ->schema([
                            FileUpload::make('logo')
                                ->label(__('partner.gas_station.fields.logo'))
                                ->image()
                                ->disk('public')
                                ->directory('gas-stations/logos')
                                ->imagePreviewHeight('150')
                                ->maxSize(2048)
                                ->helperText('Empfohlen: Quadratisch, max. 2 MB (JPG/PNG)'),
                            FileUpload::make('photos')
                                ->label(__('partner.gas_station.fields.photos'))
                                ->image()
                                ->multiple()
                                ->disk('public')
                                ->directory('gas-stations/photos')
                                ->imagePreviewHeight('120')
                                ->maxFiles(10)
                                ->maxSize(5120)
                                ->reorderable()
                                ->helperText('Bis zu 10 Fotos, max. 5 MB je Bild'),
                        ])
                        ->columns(2),
                ])
                ->columnSpanFull(),
        ]);
    }

    // --- Tabelle ---

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('partner.gas_station.fields.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('street')
                    ->label(__('partner.gas_station.fields.address'))
                    ->formatStateUsing(fn ($record) => $record->full_address)
                    ->searchable(['street', 'city', 'zip'])
                    ->toggleable(),

                TextColumn::make('brand.name')
                    ->label(__('partner.gas_station.fields.brand'))
                    ->sortable()
                    ->placeholder('Keine Marke')
                    ->toggleable(),

                BooleanColumn::make('is_active')
                    ->label(__('partner.gas_station.fields.is_active'))
                    ->sortable(),

                TextColumn::make('users_count')
                    ->label(__('partner.gas_station.fields.users_count'))
                    ->counts('users')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('partner.gas_station.fields.created_at'))
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('partner.gas_station.fields.is_active'))
                    ->trueLabel('Nur aktive')
                    ->falseLabel('Nur inaktive'),
            ])
            ->actions([
                Actions\ViewAction::make(),
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
            'index' => Pages\ListGasStations::route('/'),
            'create' => Pages\CreateGasStation::route('/create'),
            'view' => Pages\ViewGasStation::route('/{record}'),
            'edit' => Pages\EditGasStation::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'brand', 'city'];
    }
}
