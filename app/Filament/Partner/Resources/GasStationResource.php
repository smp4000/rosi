<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\GasStationResource\Pages;
use App\Models\GasStation;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Actions;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament Resource fuer die Tankstellen-Verwaltung im Partner-Panel.
 * 7 Tabs: Allgemein, Adresse, Finanzen, Oeffnungszeiten, Shop, Fotos, Karte.
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

    // --- Formular (7 Tabs) ---

    /**
     * Gibt die Form-Schema-Komponenten zurueck (wiederverwendbar fuer Wizard).
     */
    public static function getFormSchema(): array
    {
        return [
            Tabs::make('Tankstelle')
                ->tabs(static::getFormTabs())
                ->columnSpanFull(),
        ];
    }

    /**
     * Gibt die Tab-Definitionen zurueck (wiederverwendbar).
     */
    public static function getFormTabs(): array
    {
        return static::buildFormTabs();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::getFormSchema());
    }

    /**
     * Baut alle 7 Tabs fuer das Tankstellen-Formular.
     */
    private static function buildFormTabs(): array
    {
        return [
                    // ========== TAB 1: ALLGEMEIN ==========
                    Tab::make(__('partner.gas_station.tabs.allgemein'))
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
                                ->required()
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->label('Markenname')
                                        ->required()
                                        ->maxLength(255),
                                ])
                                ->createOptionUsing(function (array $data): int {
                                    return \App\Models\Brand::create($data)->getKey();
                                }),
                            Select::make('ownership_type')
                                ->label(__('partner.gas_station.fields.ownership_type'))
                                ->options(__('partner.gas_station.ownership_types'))
                                ->searchable(),
                            TextInput::make('sales_channel')
                                ->label(__('partner.gas_station.fields.sales_channel'))
                                ->maxLength(100),
                            TextInput::make('district')
                                ->label(__('partner.gas_station.fields.district'))
                                ->maxLength(100),
                            TextInput::make('district_description')
                                ->label(__('partner.gas_station.fields.district_description'))
                                ->maxLength(255),
                            TextInput::make('region')
                                ->label(__('partner.gas_station.fields.region'))
                                ->maxLength(100),
                            TextInput::make('region_manager')
                                ->label(__('partner.gas_station.fields.region_manager'))
                                ->maxLength(255),
                            TextInput::make('station_number_fuel')
                                ->label(__('partner.gas_station.fields.station_number_fuel'))
                                ->maxLength(50),
                            TextInput::make('station_number_shop')
                                ->label(__('partner.gas_station.fields.station_number_shop'))
                                ->maxLength(50),
                            Toggle::make('has_toll_terminal')
                                ->label(__('partner.gas_station.fields.has_toll_terminal')),
                            Toggle::make('is_active')
                                ->label(__('partner.gas_station.fields.is_active'))
                                ->default(true),

                            // --- Betrieb & Services ---
                            Section::make('Betrieb & Services')
                                ->schema([
                                    TextInput::make('num_pumps')
                                        ->label(__('partner.gas_station.fields.num_pumps'))
                                        ->numeric()
                                        ->minValue(0),
                                    Toggle::make('has_shop')
                                        ->label(__('partner.gas_station.fields.has_shop')),
                                    Toggle::make('has_car_wash')
                                        ->label(__('partner.gas_station.fields.has_car_wash')),
                                    CheckboxList::make('services')
                                        ->label(__('partner.gas_station.fields.services'))
                                        ->options([
                                            'super'      => 'Super (E5)',
                                            'e10'        => 'E10',
                                            'diesel'     => 'Diesel',
                                            'premium'    => 'Super Plus',
                                            'lpg'        => 'LPG / Autogas',
                                            'adblue'     => 'AdBlue',
                                            'cng'        => 'Erdgas (CNG)',
                                            'h2'         => 'Wasserstoff (H2)',
                                            'ev'         => 'Elektro-Laden',
                                            'tire'       => 'Reifenservice',
                                            'oil'        => 'Oelwechsel',
                                            'bakery'     => 'Backshop',
                                            'cafe'       => 'Cafe / Restaurant',
                                            'atm'        => 'Geldautomat',
                                            'car_rental' => 'Autovermietung',
                                        ])
                                        ->columns(3)
                                        ->columnSpanFull(),
                                    Textarea::make('notes')
                                        ->label(__('partner.gas_station.fields.notes'))
                                        ->rows(3)
                                        ->maxLength(2000)
                                        ->columnSpanFull(),
                                ])
                                ->columns(2)
                                ->columnSpanFull()
                                ->collapsible(),
                        ])
                        ->columns(2),

                    // ========== TAB 2: ADRESSE ==========
                    Tab::make(__('partner.gas_station.tabs.adresse'))
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            Select::make('academic_title')
                                ->label(__('partner.gas_station.fields.academic_title'))
                                ->options(__('partner.gas_station.academic_titles'))
                                ->searchable(),
                            Placeholder::make('spacer_title')
                                ->label('')
                                ->content(''),
                            TextInput::make('contact_last_name')
                                ->label(__('partner.gas_station.fields.contact_last_name'))
                                ->required()
                                ->maxLength(255),
                            TextInput::make('contact_first_name')
                                ->label(__('partner.gas_station.fields.contact_first_name'))
                                ->required()
                                ->maxLength(255),
                            TextInput::make('street')
                                ->label(__('partner.gas_station.fields.street'))
                                ->required()
                                ->maxLength(255),
                            TextInput::make('house_number')
                                ->label(__('partner.gas_station.fields.house_number'))
                                ->maxLength(20),
                            TextInput::make('zip')
                                ->label(__('partner.gas_station.fields.zip'))
                                ->required()
                                ->maxLength(10),
                            TextInput::make('city')
                                ->label(__('partner.gas_station.fields.city'))
                                ->required()
                                ->maxLength(255),
                            TextInput::make('district_part')
                                ->label(__('partner.gas_station.fields.district_part'))
                                ->maxLength(100),
                            TextInput::make('state')
                                ->label(__('partner.gas_station.fields.state'))
                                ->maxLength(100),
                            TextInput::make('country')
                                ->label(__('partner.gas_station.fields.country'))
                                ->default('DE')
                                ->maxLength(2),
                            Placeholder::make('spacer_country')
                                ->label('')
                                ->content(''),
                            TextInput::make('phone')
                                ->label(__('partner.gas_station.fields.phone'))
                                ->tel()
                                ->required()
                                ->maxLength(50),
                            TextInput::make('fax')
                                ->label(__('partner.gas_station.fields.fax'))
                                ->tel()
                                ->maxLength(50),
                            TextInput::make('email')
                                ->label(__('partner.gas_station.fields.email'))
                                ->email()
                                ->maxLength(255),
                            TextInput::make('website')
                                ->label(__('partner.gas_station.fields.website'))
                                ->url()
                                ->maxLength(255)
                                ->prefix('https://'),
                            // Anrede Anschrift (automatisch zusammengesetzt)
                            Placeholder::make('salutation_address')
                                ->label(__('partner.gas_station.fields.salutation_address'))
                                ->content(fn (?Model $record) => $record?->salutation_address ?? '-')
                                ->columnSpan(2),
                        ])
                        ->columns(2),

                    // ========== TAB 3: FINANZEN ==========
                    Tab::make(__('partner.gas_station.tabs.finanzen'))
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Repeater::make('bankAccounts')
                                ->label(__('partner.gas_station.fields.bank_accounts'))
                                ->relationship()
                                ->schema([
                                    TextInput::make('iban')
                                        ->label(__('partner.gas_station.fields.iban'))
                                        ->required()
                                        ->maxLength(34),
                                    TextInput::make('bank_name')
                                        ->label(__('partner.gas_station.fields.bank_name'))
                                        ->maxLength(255),
                                    TextInput::make('bic')
                                        ->label(__('partner.gas_station.fields.bic'))
                                        ->maxLength(11),
                                    TextInput::make('description')
                                        ->label(__('partner.gas_station.fields.bank_description'))
                                        ->maxLength(255),
                                    Select::make('account_type')
                                        ->label(__('partner.gas_station.fields.account_type'))
                                        ->options(__('partner.gas_station.account_types'))
                                        ->searchable(),
                                ])
                                ->columns(2)
                                ->defaultItems(0)
                                ->addActionLabel('Bankkonto hinzufuegen')
                                ->reorderable(false)
                                ->columnSpanFull(),
                        ]),

                    // ========== TAB 4: OEFFNUNGSZEITEN ==========
                    Tab::make(__('partner.gas_station.tabs.oeffnungszeiten'))
                        ->icon('heroicon-o-clock')
                        ->schema([
                            // 24-Stunden Toggle
                            Toggle::make('opening_hours.is_24h')
                                ->label('24 Stunden / Rund um die Uhr')
                                ->live()
                                ->columnSpanFull(),

                            // Live-Status
                            View::make('filament.components.gas-station-opening-status')
                                ->columnSpanFull(),

                            // Montag bis Sonntag (je 2 nebeneinander, nur wenn nicht 24h)
                            ...self::buildOpeningHoursFields(),

                            // Berechnete Wochenstunden (nur wenn nicht 24h)
                            View::make('filament.components.gas-station-weekly-hours')
                                ->columnSpanFull()
                                ->visible(fn (Get $get): bool => ! $get('opening_hours.is_24h')),

                            // Erstoeffnungs-Daten
                            DatePicker::make('first_opening_ok')
                                ->label(__('partner.gas_station.fields.first_opening_ok'))
                                ->displayFormat('d.m.Y'),
                            DatePicker::make('first_opening_dk')
                                ->label(__('partner.gas_station.fields.first_opening_dk'))
                                ->displayFormat('d.m.Y'),
                        ])
                        ->columns(2),

                    // ========== TAB 5: SHOP ==========
                    Tab::make(__('partner.gas_station.tabs.shop'))
                        ->icon('heroicon-o-shopping-bag')
                        ->schema([
                            TextInput::make('shop_size')
                                ->label(__('partner.gas_station.fields.shop_size'))
                                ->maxLength(50),
                            Select::make('shop_type')
                                ->label(__('partner.gas_station.fields.shop_type'))
                                ->options(__('partner.gas_station.shop_types'))
                                ->searchable(),
                            Select::make('shop_class')
                                ->label(__('partner.gas_station.fields.shop_class'))
                                ->options(__('partner.gas_station.shop_classes')),
                            DatePicker::make('shop_setup_date')
                                ->label(__('partner.gas_station.fields.shop_setup_date'))
                                ->displayFormat('d.m.Y'),
                            Select::make('nielsen_area')
                                ->label(__('partner.gas_station.fields.nielsen_area'))
                                ->options(__('partner.gas_station.nielsen_areas'))
                                ->searchable(),
                            TextInput::make('price_region')
                                ->label(__('partner.gas_station.fields.price_region'))
                                ->maxLength(50),
                            Select::make('assortment_level')
                                ->label(__('partner.gas_station.fields.assortment_level'))
                                ->options(__('partner.gas_station.assortment_levels')),
                            TextInput::make('shop_partner')
                                ->label(__('partner.gas_station.fields.shop_partner'))
                                ->maxLength(255),
                            TextInput::make('shop_operation_number')
                                ->label(__('partner.gas_station.fields.shop_operation_number'))
                                ->maxLength(50),
                        ])
                        ->columns(2),

                    // ========== TAB 6: FOTOS ==========
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

                    // ========== TAB 7: KARTE ==========
                    Tab::make(__('partner.gas_station.tabs.karte'))
                        ->icon('heroicon-o-globe-alt')
                        ->schema([
                            TextInput::make('latitude')
                                ->label(__('partner.gas_station.fields.latitude'))
                                ->numeric()
                                ->step(0.00000001),
                            TextInput::make('longitude')
                                ->label(__('partner.gas_station.fields.longitude'))
                                ->numeric()
                                ->step(0.00000001),
                            View::make('filament.components.gas-station-map')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
        ];
    }

    /**
     * Baut die Oeffnungszeiten-Felder fuer alle 7 Wochentage.
     * Feste Struktur mit Standardwerten (Mo-Fr 06:00-21:00, Sa 07:00, So 08:00).
     */
    protected static function buildOpeningHoursFields(): array
    {
        $days = [
            'monday'    => ['06:00', '21:00'],
            'tuesday'   => ['06:00', '21:00'],
            'wednesday' => ['06:00', '21:00'],
            'thursday'  => ['06:00', '21:00'],
            'friday'    => ['06:00', '21:00'],
            'saturday'  => ['07:00', '21:00'],
            'sunday'    => ['08:00', '21:00'],
        ];

        $fields = [];

        foreach ($days as $day => [$defaultOpen, $defaultClose]) {
            $fields[] = Section::make(__("partner.gas_station.days.{$day}"))
                ->schema([
                    TextInput::make("opening_hours.{$day}.open")
                        ->label(__('partner.gas_station.fields.open_time'))
                        ->type('time')
                        ->default($defaultOpen),
                    TextInput::make("opening_hours.{$day}.close")
                        ->label(__('partner.gas_station.fields.close_time'))
                        ->type('time')
                        ->default($defaultClose),
                ])
                ->columns(2)
                ->compact()
                ->columnSpan(1)
                ->visible(fn (Get $get): bool => ! $get('opening_hours.is_24h'));
        }

        return $fields;
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

                TextColumn::make('ownership_type')
                    ->label(__('partner.gas_station.fields.ownership_type'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
        return ['name', 'city', 'station_number_fuel'];
    }
}
