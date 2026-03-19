<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\GasStationResource\Pages;
use App\Models\GasStation;
use App\Models\User;
use Filament\Forms\Components\Hidden;
use App\Models\LookupValue;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
use Illuminate\Support\Facades\Http;

/**
 * Filament Resource fuer die Tankstellen-Verwaltung im Partner-Panel.
 * 8 Tabs: Adresse, Allgemein, Finanzen, Oeffnungszeiten, Shop, Fotos, Wettbewerb, Karte.
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
     * Baut alle 8 Tabs fuer das Tankstellen-Formular.
     */
    private static function buildFormTabs(): array
    {
        return [
                    // ========== TAB 1: ADRESSE ==========
                    Tab::make(__('partner.gas_station.tabs.adresse'))
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            // --- Ansprechpartner ---
                            Section::make('Ansprechpartner')
                                ->schema([
                                    Select::make('academic_title')
                                        ->label(__('partner.gas_station.fields.academic_title'))
                                        ->options(__('partner.gas_station.academic_titles'))
                                        ->searchable(),
                                    TextInput::make('contact_first_name')
                                        ->label(__('partner.gas_station.fields.contact_first_name'))
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('contact_last_name')
                                        ->label(__('partner.gas_station.fields.contact_last_name'))
                                        ->required()
                                        ->maxLength(255),
                                ])
                                ->columns(3)
                                ->columnSpanFull(),

                            // --- Adresse ---
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
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    self::fillAddressFromNominatim($get, $set);
                                }),
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

                            // --- Kontakt ---
                            Section::make('Kontakt')
                                ->schema([
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
                                ])
                                ->columns(2)
                                ->columnSpanFull(),

                            // Anrede Anschrift (automatisch zusammengesetzt)
                            Placeholder::make('salutation_address')
                                ->label(__('partner.gas_station.fields.salutation_address'))
                                ->content(fn (?Model $record) => $record?->salutation_address ?? '-')
                                ->columnSpan(2),
                        ])
                        ->columns(2),

                    // ========== TAB 2: ALLGEMEIN ==========
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
                                        ->maxLength(34)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Set $set, ?string $state) {
                                            if ($state && strlen(preg_replace('/\s+/', '', $state)) >= 15) {
                                                $iban = preg_replace('/\s+/', '', $state);
                                                $bankData = \App\Filament\Partner\Resources\EmployeeResource::lookupBankDataPublic($iban);
                                                if ($bankData['bic']) $set('bic', $bankData['bic']);
                                                if ($bankData['bankName']) $set('bank_name', $bankData['bankName']);
                                            }
                                        })
                                        ->hintAction(
                                            \Filament\Actions\Action::make('iban_rechner')
                                                ->label('IBAN-Rechner')
                                                ->icon('heroicon-o-calculator')
                                                ->color('gray')
                                                ->size('sm')
                                                ->form([
                                                    TextInput::make('blz')
                                                        ->label('Bankleitzahl (BLZ)')
                                                        ->required()
                                                        ->maxLength(8)
                                                        ->placeholder('8-stellige BLZ'),
                                                    TextInput::make('kontonummer')
                                                        ->label('Kontonummer')
                                                        ->required()
                                                        ->maxLength(10)
                                                        ->placeholder('Max. 10 Stellen'),
                                                ])
                                                ->modalHeading('IBAN aus BLZ + Kontonummer berechnen')
                                                ->modalSubmitActionLabel('Uebernehmen')
                                                ->modalWidth('md')
                                                ->action(function (array $data, Set $set) {
                                                    $iban = \App\Filament\Partner\Resources\EmployeeResource::calculateGermanIbanPublic(
                                                        $data['blz'], $data['kontonummer']
                                                    );
                                                    if (! $iban) {
                                                        \Filament\Notifications\Notification::make()
                                                            ->title('Ungueltige BLZ oder Kontonummer')
                                                            ->danger()
                                                            ->send();
                                                        return;
                                                    }
                                                    $set('iban', $iban);
                                                    $bankData = \App\Filament\Partner\Resources\EmployeeResource::lookupBankDataPublic($iban);
                                                    if ($bankData['bic']) $set('bic', $bankData['bic']);
                                                    if ($bankData['bankName']) $set('bank_name', $bankData['bankName']);

                                                    \Filament\Notifications\Notification::make()
                                                        ->title('IBAN berechnet')
                                                        ->body($iban . ($bankData['bankName'] ? ' — ' . $bankData['bankName'] : ''))
                                                        ->success()
                                                        ->send();
                                                })
                                        ),
                                    TextInput::make('bank_name')
                                        ->label(__('partner.gas_station.fields.bank_name'))
                                        ->maxLength(255)
                                        ->placeholder('Wird automatisch ermittelt'),
                                    TextInput::make('bic')
                                        ->label(__('partner.gas_station.fields.bic'))
                                        ->maxLength(11)
                                        ->placeholder('Wird automatisch ermittelt'),
                                    TextInput::make('description')
                                        ->label(__('partner.gas_station.fields.bank_description'))
                                        ->maxLength(255),
                                    Select::make('account_type')
                                        ->label(__('partner.gas_station.fields.account_type'))
                                        ->options(function () {
                                            $defaults = __('partner.gas_station.account_types');
                                            $tenant = \App\Models\Tenant::find(session('tenant_id'));
                                            $custom = $tenant?->settings['custom_account_types'] ?? [];
                                            foreach ($custom as $type) {
                                                $defaults[$type] = $type;
                                            }
                                            return $defaults;
                                        })
                                        ->searchable()
                                        ->createOptionForm([
                                            TextInput::make('name')
                                                ->label('Neuer Kontotyp')
                                                ->required()
                                                ->maxLength(100)
                                                ->placeholder('z.B. VWL-Konto, Sparkonto'),
                                        ])
                                        ->createOptionUsing(function (array $data): string {
                                            $name = trim($data['name']);
                                            $tenant = \App\Models\Tenant::find(session('tenant_id'));
                                            if ($tenant) {
                                                $settings = $tenant->settings ?? [];
                                                $custom = $settings['custom_account_types'] ?? [];
                                                if (! in_array($name, $custom)) {
                                                    $custom[] = $name;
                                                    $settings['custom_account_types'] = $custom;
                                                    $tenant->update(['settings' => $settings]);
                                                }
                                            }
                                            return $name;
                                        }),
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

                            // Berechnete Wochenstunden
                            View::make('filament.components.gas-station-weekly-hours')
                                ->columnSpanFull(),

                            // Erstoeffnungs-Daten
                            DatePicker::make('first_opening_ok')
                                ->label(__('partner.gas_station.fields.first_opening_ok'))
                                ->displayFormat('d.m.Y'),
                            DatePicker::make('first_opening_dk')
                                ->label(__('partner.gas_station.fields.first_opening_dk'))
                                ->displayFormat('d.m.Y'),
                        ])
                        ->columns(2),

                    // ========== TAB 5: SHOP & BETRIEB ==========
                    Tab::make(__('partner.gas_station.tabs.shop'))
                        ->icon('heroicon-o-shopping-bag')
                        ->schema([

                            // ---- SEKTION 1: TANKDETAILS ----
                            Section::make(__('partner.gas_station.sections.tank_details'))
                                ->icon('heroicon-o-fire')
                                ->schema([
                                    TextInput::make('num_pumps')
                                        ->label(__('partner.gas_station.fields.num_pumps'))
                                        ->numeric()
                                        ->minValue(1)
                                        ->default(1),
                                    TagsInput::make('fuel_types')
                                        ->label(__('partner.gas_station.fields.fuel_types'))
                                        ->suggestions([
                                            'Super (E5)',
                                            'E10',
                                            'Diesel',
                                            'Super Plus',
                                            'LPG / Autogas',
                                            'AdBlue',
                                            'Erdgas (CNG)',
                                            'Wasserstoff (H2)',
                                            'Elektro-Laden',
                                            'Aral Ultimate 102',
                                            'Shell V-Power Racing',
                                            'BP Ultimate Diesel',
                                            'Total Excellium',
                                            'OMV MaxxMotion',
                                            'Jet Premium',
                                            'HEM Optimal',
                                        ])
                                        ->placeholder('Kraftstoff eingeben oder auswaehlen...')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2)
                                ->columnSpanFull()
                                ->collapsible(),

                            // ---- SEKTION 2: WASCHANLAGE ----
                            Section::make(__('partner.gas_station.sections.car_wash'))
                                ->icon('heroicon-o-beaker')
                                ->schema([
                                    Toggle::make('has_car_wash')
                                        ->label(__('partner.gas_station.fields.has_car_wash'))
                                        ->live()
                                        ->columnSpanFull(),

                                    Toggle::make('car_wash_details.has_drive_through')
                                        ->label(__('partner.gas_station.fields.cw_has_drive_through'))
                                        ->visible(fn (Get $get): bool => (bool) $get('has_car_wash')),
                                    Toggle::make('car_wash_details.has_underbody_wash')
                                        ->label(__('partner.gas_station.fields.cw_has_underbody_wash'))
                                        ->visible(fn (Get $get): bool => (bool) $get('has_car_wash')),

                                    Select::make('car_wash_details.brand_id')
                                        ->label(__('partner.gas_station.fields.cw_brand'))
                                        ->options(fn () => LookupValue::byCategory('car_wash_brand')->pluck('value', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->createOptionForm([
                                            TextInput::make('value')
                                                ->label('Markenname')
                                                ->required()
                                                ->maxLength(255),
                                        ])
                                        ->createOptionUsing(function (array $data): int {
                                            return LookupValue::create([
                                                'category' => 'car_wash_brand',
                                                'value' => $data['value'],
                                            ])->getKey();
                                        })
                                        ->visible(fn (Get $get): bool => (bool) $get('has_car_wash')),

                                    Select::make('car_wash_details.type_id')
                                        ->label(__('partner.gas_station.fields.cw_type'))
                                        ->options(fn () => LookupValue::byCategory('car_wash_type')->pluck('value', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->createOptionForm([
                                            TextInput::make('value')
                                                ->label('Anlagentyp')
                                                ->required()
                                                ->maxLength(255),
                                        ])
                                        ->createOptionUsing(function (array $data): int {
                                            return LookupValue::create([
                                                'category' => 'car_wash_type',
                                                'value' => $data['value'],
                                            ])->getKey();
                                        })
                                        ->visible(fn (Get $get): bool => (bool) $get('has_car_wash')),

                                    TextInput::make('car_wash_details.height')
                                        ->label(__('partner.gas_station.fields.cw_height'))
                                        ->numeric()
                                        ->step(0.01)
                                        ->suffix('m')
                                        ->visible(fn (Get $get): bool => (bool) $get('has_car_wash')),
                                    TextInput::make('car_wash_details.width')
                                        ->label(__('partner.gas_station.fields.cw_width'))
                                        ->numeric()
                                        ->step(0.01)
                                        ->suffix('m')
                                        ->visible(fn (Get $get): bool => (bool) $get('has_car_wash')),

                                    Toggle::make('car_wash_details.has_ticket_system')
                                        ->label(__('partner.gas_station.fields.cw_has_ticket_system'))
                                        ->visible(fn (Get $get): bool => (bool) $get('has_car_wash')),
                                    Toggle::make('car_wash_details.has_easy_carwash_pro')
                                        ->label(__('partner.gas_station.fields.cw_has_easy_carwash_pro'))
                                        ->visible(fn (Get $get): bool => (bool) $get('has_car_wash')),

                                    Textarea::make('car_wash_details.notes')
                                        ->label(__('partner.gas_station.fields.cw_notes'))
                                        ->rows(3)
                                        ->maxLength(2000)
                                        ->columnSpanFull()
                                        ->visible(fn (Get $get): bool => (bool) $get('has_car_wash')),
                                ])
                                ->columns(2)
                                ->columnSpanFull()
                                ->collapsible(),

                            // ---- SEKTION 3: SHOP-DETAILS ----
                            Section::make(__('partner.gas_station.sections.shop_details'))
                                ->icon('heroicon-o-shopping-cart')
                                ->schema([
                                    Toggle::make('has_shop')
                                        ->label(__('partner.gas_station.fields.has_shop'))
                                        ->live()
                                        ->columnSpanFull(),

                                    TextInput::make('shop_size')
                                        ->label(__('partner.gas_station.fields.shop_size'))
                                        ->maxLength(50)
                                        ->visible(fn (Get $get): bool => (bool) $get('has_shop')),
                                    Select::make('shop_type')
                                        ->label(__('partner.gas_station.fields.shop_type'))
                                        ->options(__('partner.gas_station.shop_types'))
                                        ->searchable()
                                        ->visible(fn (Get $get): bool => (bool) $get('has_shop')),
                                    Select::make('shop_class')
                                        ->label(__('partner.gas_station.fields.shop_class'))
                                        ->options(__('partner.gas_station.shop_classes'))
                                        ->visible(fn (Get $get): bool => (bool) $get('has_shop')),
                                    DatePicker::make('shop_setup_date')
                                        ->label(__('partner.gas_station.fields.shop_setup_date'))
                                        ->displayFormat('d.m.Y')
                                        ->visible(fn (Get $get): bool => (bool) $get('has_shop')),
                                    Select::make('nielsen_area')
                                        ->label(__('partner.gas_station.fields.nielsen_area'))
                                        ->options(__('partner.gas_station.nielsen_areas'))
                                        ->searchable()
                                        ->visible(fn (Get $get): bool => (bool) $get('has_shop')),
                                    TextInput::make('price_region')
                                        ->label(__('partner.gas_station.fields.price_region'))
                                        ->maxLength(50)
                                        ->visible(fn (Get $get): bool => (bool) $get('has_shop')),
                                    Select::make('assortment_level')
                                        ->label(__('partner.gas_station.fields.assortment_level'))
                                        ->options(__('partner.gas_station.assortment_levels'))
                                        ->visible(fn (Get $get): bool => (bool) $get('has_shop')),
                                    TextInput::make('shop_partner')
                                        ->label(__('partner.gas_station.fields.shop_partner'))
                                        ->maxLength(255)
                                        ->visible(fn (Get $get): bool => (bool) $get('has_shop')),
                                    TextInput::make('shop_operation_number')
                                        ->label(__('partner.gas_station.fields.shop_operation_number'))
                                        ->maxLength(50)
                                        ->visible(fn (Get $get): bool => (bool) $get('has_shop')),

                                    // Shop-Services (Backshop, Geldautomat, Cafe)
                                    CheckboxList::make('services')
                                        ->label(__('partner.gas_station.fields.shop_services'))
                                        ->options([
                                            'bakery' => 'Backshop',
                                            'atm'    => 'Geldautomat',
                                            'cafe'   => 'Cafe / Restaurant',
                                        ])
                                        ->columns(3)
                                        ->columnSpanFull()
                                        ->visible(fn (Get $get): bool => (bool) $get('has_shop')),
                                ])
                                ->columns(2)
                                ->columnSpanFull()
                                ->collapsible(),

                            // ---- SEKTION 4: ZUSATZGESCHAEFTE ----
                            Section::make(__('partner.gas_station.sections.additional_businesses'))
                                ->icon('heroicon-o-building-office')
                                ->schema([
                                    CheckboxList::make('additional_businesses')
                                        ->label(__('partner.gas_station.fields.additional_businesses'))
                                        ->options([
                                            // Paket & Post
                                            'hermes'         => 'Hermes Paketshop',
                                            'ups'            => 'UPS Access Point',
                                            'dhl'            => 'DHL Paketshop',
                                            'dpd'            => 'DPD Pickup',
                                            'gls'            => 'GLS Paketshop',
                                            'amazon_locker'  => 'Amazon Locker',
                                            // Lotto & Gluecksspiel
                                            'lotto'          => 'Lotto',
                                            'toto'           => 'Toto',
                                            'sportwetten'    => 'Sportwetten',
                                            // Werkstatt & KFZ
                                            'tire_service'   => 'Reifenservice',
                                            'master_shop'    => 'Meisterbetrieb',
                                            'car_rental'     => 'Autovermietung',
                                            'oil_service'    => 'Oelservice',
                                            'used_cars'      => 'Gebrauchtwagenhandel',
                                            'tuev_dekra'     => 'TUEV / Dekra-Pruefstelle',
                                        ])
                                        ->columns(3)
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull()
                                ->collapsible(),

                            // ---- ALLGEMEINE NOTIZEN ----
                            Textarea::make('notes')
                                ->label(__('partner.gas_station.fields.notes'))
                                ->rows(3)
                                ->maxLength(2000)
                                ->columnSpanFull(),
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

                    // ========== TAB 7: WETTBEWERB ==========
                    Tab::make(__('partner.gas_station.tabs.wettbewerb'))
                        ->icon('heroicon-o-scale')
                        ->schema([

                            // --- Eigene Kraftstoffpreise ---
                            Section::make('Eigene Kraftstoffpreise')
                                ->icon('heroicon-o-currency-euro')
                                ->description('Aktuelle Preise Ihrer Tankstelle fuer den Kartenvergleich')
                                ->schema([
                                    TextInput::make('price_super')
                                        ->label('Super E5')
                                        ->numeric()
                                        ->step(0.001)
                                        ->suffix('€/L')
                                        ->placeholder('z.B. 1.859')
                                        ->inputMode('decimal'),
                                    TextInput::make('price_e10')
                                        ->label('Super E10')
                                        ->numeric()
                                        ->step(0.001)
                                        ->suffix('€/L')
                                        ->placeholder('z.B. 1.799')
                                        ->inputMode('decimal'),
                                    TextInput::make('price_diesel')
                                        ->label('Diesel')
                                        ->numeric()
                                        ->step(0.001)
                                        ->suffix('€/L')
                                        ->placeholder('z.B. 1.699')
                                        ->inputMode('decimal'),
                                ])
                                ->columns(3)
                                ->columnSpanFull(),

                            // --- Suchbereich ---
                            Section::make(__('partner.gas_station.sections.competitor_search'))
                                ->icon('heroicon-o-magnifying-glass')
                                ->schema([
                                    TextInput::make('competitor_search_plz')
                                        ->label(__('partner.gas_station.fields.competitor_search_plz'))
                                        ->placeholder('z.B. 36039')
                                        ->maxLength(5)
                                        ->minLength(5)
                                        ->live(debounce: 800)
                                        ->dehydrated(false)
                                        ->helperText('5-stellige PLZ — Suche startet automatisch'),

                                    Select::make('competitor_select')
                                        ->label(__('partner.gas_station.fields.competitor_stations'))
                                        ->options(function (Get $get): array {
                                            $plz = $get('competitor_search_plz');
                                            if (! $plz || strlen($plz) !== 5 || ! ctype_digit($plz)) {
                                                return [];
                                            }

                                            $stations = app(\App\Services\BenzinpreisService::class)->searchByPlz($plz);

                                            // Eigene Station rausfiltern (Name + Stadt vergleichen)
                                            $ownName = strtolower(trim($get('name') ?? ''));
                                            $ownStreet = strtolower(trim($get('street') ?? ''));

                                            $options = [];
                                            foreach ($stations as $s) {
                                                // Eigene Station ueberspringen
                                                $sName = strtolower(trim($s['name'] ?? ''));
                                                $sStreet = strtolower(trim($s['street'] ?? ''));
                                                if ($ownName && $ownStreet && str_contains($sName, $ownName) && $sStreet && str_contains($sStreet, $ownStreet)) {
                                                    continue;
                                                }

                                                $key = "{$s['hash']}|{$s['slug']}";
                                                $label = $s['name'];
                                                $addressParts = array_filter([$s['street'] ?? '', $s['city'] ?? '']);
                                                if ($addressParts) {
                                                    $label .= ' · ' . implode(', ', $addressParts);
                                                }
                                                $options[$key] = $label;
                                            }

                                            return $options;
                                        })
                                        ->placeholder('Station auswaehlen...')
                                        ->searchable()
                                        ->live()
                                        ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                            if (! $state) {
                                                return;
                                            }

                                            $current = $get('competitors') ?? [];
                                            if (count($current) >= 8) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Maximum erreicht')
                                                    ->body('Maximal 8 Wettbewerber moeglich.')
                                                    ->warning()
                                                    ->send();
                                                $set('competitor_select', null);
                                                return;
                                            }

                                            [$hash, $slug] = explode('|', $state, 2);
                                            $details = app(\App\Services\BenzinpreisService::class)->fetchStationDetails($hash, $slug);

                                            if (! $details) {
                                                \Filament\Notifications\Notification::make()
                                                    ->title('Fehler')
                                                    ->body('Stationsdaten konnten nicht abgerufen werden.')
                                                    ->danger()
                                                    ->send();
                                                $set('competitor_select', null);
                                                return;
                                            }

                                            $name = $details['name'] ?? '';
                                            if (str_contains($name, '·')) {
                                                $name = trim(explode('·', $name)[0]);
                                            }

                                            // Duplikat-Pruefung (Name + Strasse)
                                            $newStreet = strtolower(trim(($details['street'] ?? '') . ' ' . ($details['house_number'] ?? '')));
                                            $newName = strtolower(trim($name));
                                            foreach ($current as $existing) {
                                                if (strtolower(trim($existing['name'] ?? '')) === $newName
                                                    && strtolower(trim($existing['street'] ?? '')) === $newStreet) {
                                                    \Filament\Notifications\Notification::make()
                                                        ->title('Bereits vorhanden')
                                                        ->body("{$name} ist bereits in der Liste.")
                                                        ->warning()
                                                        ->send();
                                                    $set('competitor_select', null);
                                                    return;
                                                }
                                            }

                                            // Entfernung berechnen (Haversine)
                                            $distance = null;
                                            $ownLat = (float) ($get('latitude') ?? 0);
                                            $ownLng = (float) ($get('longitude') ?? 0);
                                            $compLat = (float) ($details['lat'] ?? 0);
                                            $compLng = (float) ($details['lng'] ?? 0);
                                            if ($ownLat && $ownLng && $compLat && $compLng) {
                                                $earthRadius = 6371;
                                                $dLat = deg2rad($compLat - $ownLat);
                                                $dLng = deg2rad($compLng - $ownLng);
                                                $a = sin($dLat / 2) ** 2 + cos(deg2rad($ownLat)) * cos(deg2rad($compLat)) * sin($dLng / 2) ** 2;
                                                $distance = round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 1);
                                            }

                                            // Preise extrahieren
                                            $prices = $details['prices'] ?? [];

                                            $current[(string) \Illuminate\Support\Str::uuid()] = [
                                                'name' => $name,
                                                'brand' => $details['brand'] ?? '',
                                                'street' => trim(($details['street'] ?? '') . ' ' . ($details['house_number'] ?? '')),
                                                'city' => $details['city'] ?? '',
                                                'zip' => $details['zip'] ?? '',
                                                'distance' => $distance,
                                                'lat' => $details['lat'] ?? null,
                                                'lng' => $details['lng'] ?? null,
                                                'price_e10' => $prices['E10'] ?? null,
                                                'price_diesel' => $prices['Diesel'] ?? null,
                                                'price_super' => $prices['Super'] ?? null,
                                                'notes' => '',
                                            ];

                                            $set('competitors', $current);
                                            $set('competitor_select', null);

                                            // Notification mit Preisen
                                            $distText = $distance ? " ({$distance} km)" : '';
                                            $priceInfo = [];
                                            if ($prices['E10'] ?? null) { $priceInfo[] = "E10: {$prices['E10']}€"; }
                                            if ($prices['Diesel'] ?? null) { $priceInfo[] = "Diesel: {$prices['Diesel']}€"; }
                                            $priceText = $priceInfo ? ' — ' . implode(', ', $priceInfo) : '';
                                            \Filament\Notifications\Notification::make()
                                                ->title('Wettbewerber hinzugefuegt')
                                                ->body("{$name}{$distText}{$priceText}")
                                                ->success()
                                                ->send();
                                        })
                                        ->dehydrated(false)
                                        ->columnSpanFull(),
                                ])
                                ->columns(2)
                                ->columnSpanFull()
                                ->collapsed(),

                            // --- Wettbewerber-Karten (Drag = Prioritaet) ---
                            Repeater::make('competitors')
                                ->label(__('partner.gas_station.fields.competitors'))
                                ->helperText('Reihenfolge = Prioritaet. Oberste Karte = hoechste Prioritaet. Per Drag & Drop verschieben.')
                                ->schema([
                                    TextInput::make('name')
                                        ->label('Name')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('brand')
                                        ->label('Marke')
                                        ->maxLength(100),
                                    TextInput::make('street')
                                        ->label('Adresse')
                                        ->maxLength(255),
                                    TextInput::make('city')
                                        ->label('Stadt')
                                        ->maxLength(100),
                                    TextInput::make('distance')
                                        ->label('Entfernung')
                                        ->numeric()
                                        ->step(0.1)
                                        ->suffix('km'),
                                    Textarea::make('notes')
                                        ->label('Notizen')
                                        ->rows(1)
                                        ->maxLength(500),
                                    Hidden::make('lat'),
                                    Hidden::make('lng'),
                                    Hidden::make('zip'),
                                    Hidden::make('price_e10'),
                                    Hidden::make('price_diesel'),
                                    Hidden::make('price_super'),
                                ])
                                ->columns(6)
                                ->maxItems(8)
                                ->defaultItems(0)
                                ->addActionLabel('Manuell hinzufuegen')
                                ->reorderable()
                                ->collapsible()
                                ->itemLabel(function (array $state): ?string {
                                    $brand = $state['brand'] ?? '';
                                    $name = $state['name'] ?? '';
                                    // Doppelte Marke entfernen (z.B. "ARAL Fulda" mit brand "ARAL" -> "ARAL Fulda")
                                    if ($brand && $name && stripos($name, $brand) === 0) {
                                        $name = trim(substr($name, strlen($brand)));
                                        if (stripos($name, 'Tankstelle') === 0) {
                                            $name = trim(substr($name, 10));
                                        }
                                    }
                                    $label = $brand ? $brand . ' ' . $name : $name;
                                    if ($state['street'] ?? '') {
                                        $label .= ' — ' . $state['street'];
                                    }
                                    if ($state['city'] ?? '') {
                                        $label .= ', ' . $state['city'];
                                    }
                                    if ($state['distance'] ?? null) {
                                        $label .= ' (' . $state['distance'] . ' km)';
                                    }
                                    return $label ?: 'Neuer Wettbewerber';
                                })
                                ->columnSpanFull(),
                        ]),

                    // ========== TAB 8: KARTE ==========
                    // ========== TAB: MITARBEITER ==========
                    Tab::make('Mitarbeiter')
                        ->icon('heroicon-o-users')
                        ->schema([
                            Placeholder::make('employees_list')
                                ->hiddenLabel()
                                ->content(function ($record) {
                                    if (! $record) {
                                        return new \Illuminate\Support\HtmlString(
                                            '<p style="color:#9ca3af;">Speichern Sie die Tankstelle zuerst, um Mitarbeiter zuzuordnen.</p>'
                                        );
                                    }

                                    $employees = $record->users()
                                        ->where('type', 'employee')
                                        ->withPivot(['station_role', 'is_primary', 'assigned_at'])
                                        ->orderBy('last_name')
                                        ->get();

                                    if ($employees->isEmpty()) {
                                        return new \Illuminate\Support\HtmlString(
                                            '<div style="text-align:center;padding:2rem 0;color:#9ca3af;">'
                                            . '<p style="font-size:1.1rem;">Keine Mitarbeiter zugeordnet.</p>'
                                            . '<p style="font-size:0.85rem;margin-top:4px;">Ordnen Sie Mitarbeiter ueber die Mitarbeiter-Verwaltung dieser Tankstelle zu.</p>'
                                            . '</div>'
                                        );
                                    }

                                    $roleLabels = [
                                        'station_manager' => 'Stationsleiter',
                                        'shift_leader' => 'Schichtleiter',
                                        'cashier' => 'Kassierer',
                                        'attendant' => 'Tankwart',
                                        'trainee' => 'Auszubildende/r',
                                        'cleaner' => 'Reinigungskraft',
                                        'carwash' => 'Waschanlage',
                                        'employee' => 'Mitarbeiter',
                                    ];
                                    $roleColors = [
                                        'station_manager' => 'background:#dbeafe;color:#1e40af;',
                                        'shift_leader' => 'background:#fef3c7;color:#92400e;',
                                        'cashier' => 'background:#dcfce7;color:#166534;',
                                        'attendant' => 'background:#f3f4f6;color:#374151;',
                                        'trainee' => 'background:#ede9fe;color:#5b21b6;',
                                        'cleaner' => 'background:#fce7f3;color:#9d174d;',
                                        'carwash' => 'background:#cffafe;color:#155e75;',
                                        'employee' => 'background:#e0e7ff;color:#3730a3;',
                                    ];

                                    $html = '<div style="display:flex;flex-direction:column;gap:8px;">';
                                    foreach ($employees as $emp) {
                                        $role = $emp->pivot->station_role;
                                        $roleName = $roleLabels[$role] ?? ($role ?: 'Keine Rolle');
                                        $roleColor = $roleColors[$role] ?? 'background:#f3f4f6;color:#374151;';
                                        $primary = $emp->pivot->is_primary ? '<span style="font-size:11px;color:#059669;font-weight:600;margin-left:6px;">Hauptstandort</span>' : '';
                                        $since = $emp->pivot->assigned_at ? ' seit ' . \Carbon\Carbon::parse($emp->pivot->assigned_at)->format('d.m.Y') : '';
                                        $editUrl = EmployeeResource::getUrl('edit', ['record' => $emp->id]);
                                        $active = $emp->is_active
                                            ? '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e;margin-right:8px;" title="Aktiv"></span>'
                                            : '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#ef4444;margin-right:8px;" title="Inaktiv"></span>';

                                        $html .= '<a href="' . $editUrl . '" style="display:flex;align-items:center;justify-content:space-between;border:1px solid #e5e7eb;border-radius:8px;padding:12px 16px;text-decoration:none;background:#fff;transition:all .15s;" onmouseover="this.style.borderColor=\'#93c5fd\';this.style.background=\'#f9fafb\'" onmouseout="this.style.borderColor=\'#e5e7eb\';this.style.background=\'#fff\'">';
                                        $html .= '<div style="display:flex;align-items:center;">';
                                        $html .= $active;
                                        $html .= '<div>';
                                        $html .= '<p style="margin:0;font-weight:600;color:#111827;font-size:14px;">' . e($emp->name) . '</p>';
                                        $html .= '<p style="margin:2px 0 0;font-size:12px;color:#6b7280;">' . e($emp->email) . $since . '</p>';
                                        $html .= '</div>';
                                        $html .= '</div>';
                                        $html .= '<div style="display:flex;align-items:center;gap:6px;">';
                                        $html .= $primary;
                                        $html .= '<span style="border-radius:9999px;padding:2px 10px;font-size:11px;font-weight:500;' . $roleColor . '">' . $roleName . '</span>';
                                        $html .= '</div>';
                                        $html .= '</a>';
                                    }
                                    $html .= '</div>';

                                    return new \Illuminate\Support\HtmlString($html);
                                })
                                ->columnSpanFull(),

                            // --- Mitarbeiter hinzufuegen ---
                            Section::make('Mitarbeiter hinzufuegen')
                                ->icon('heroicon-o-plus-circle')
                                ->collapsed()
                                ->schema([
                                    Select::make('_add_employee_id')
                                        ->label('Mitarbeiter')
                                        ->options(function ($record) {
                                            if (! $record) return [];
                                            $assignedIds = $record->users()->pluck('users.id');
                                            return \App\Models\User::where('tenant_id', session('tenant_id'))
                                                ->where('type', 'employee')
                                                ->whereNotIn('id', $assignedIds)
                                                ->orderBy('last_name')
                                                ->get()
                                                ->mapWithKeys(fn ($u) => [$u->id => $u->name . ' (' . $u->email . ')']);
                                        })
                                        ->searchable()
                                        ->placeholder('Mitarbeiter waehlen...')
                                        ->dehydrated(false),
                                    Select::make('_station_role')
                                        ->label('Rolle an dieser Tankstelle')
                                        ->options([
                                            'station_manager' => 'Stationsleiter',
                                            'shift_leader' => 'Schichtleiter',
                                            'cashier' => 'Kassierer',
                                            'attendant' => 'Tankwart',
                                            'trainee' => 'Auszubildende/r',
                                            'cleaner' => 'Reinigungskraft',
                                            'carwash' => 'Waschanlage',
                                            'employee' => 'Mitarbeiter',
                                        ])
                                        ->placeholder('Rolle waehlen...')
                                        ->dehydrated(false),
                                    Toggle::make('_is_primary')
                                        ->label('Hauptstandort des Mitarbeiters')
                                        ->dehydrated(false),
                                ])
                                ->columns(3)
                                ->columnSpanFull(),
                        ]),

                    // ========== TAB: KARTE ==========
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

    /**
     * Fuellt Ortsteil, Bundesland und Land per Nominatim aus PLZ+Stadt.
     */
    private static function fillAddressFromNominatim(Get $get, Set $set): void
    {
        $zip = $get('zip');
        $city = $get('city');

        if (! $zip || ! $city || strlen($zip) < 4) {
            return;
        }

        try {
            $response = Http::withoutVerifying()->timeout(5)
                ->withUserAgent('Mozilla/5.0 (ROSI-App)')
                ->get('https://nominatim.openstreetmap.org/search', [
                    'postalcode' => $zip,
                    'city' => $city,
                    'country' => 'de',
                    'format' => 'json',
                    'addressdetails' => 1,
                    'limit' => 1,
                ]);

            if (! $response->successful()) {
                return;
            }

            $data = $response->json();
            if (empty($data)) {
                return;
            }

            $address = $data[0]['address'] ?? [];

            // Bundesland
            if (! empty($address['state']) && ! $get('state')) {
                $set('state', $address['state']);
            }

            // Ortsteil (suburb > quarter > village, nur wenn unterschiedlich zur Stadt)
            $district = $address['suburb'] ?? $address['quarter'] ?? $address['village'] ?? '';
            if ($district && $district !== $city && ! $get('district_part')) {
                $set('district_part', $district);
            }

            // Land (nur wenn leer)
            if (! $get('country')) {
                $set('country', strtoupper($address['country_code'] ?? 'DE'));
            }
        } catch (\Exception $e) {
            // Stille Fehlerbehandlung - Auto-Fill ist optional
        }
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
