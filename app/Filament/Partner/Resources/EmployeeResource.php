<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\EmployeeResource\Pages;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Services\DocumentService;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Actions;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Filament Resource fuer die Mitarbeiter-Verwaltung im Partner-Panel.
 * 10-Tab DATV-Personalbogen — alle Felder editierbar.
 */
class EmployeeResource extends Resource
{
    use \App\Filament\Concerns\HasCatalogPermissions;

    /** Katalog-Schluessel fuer die Rechte-Pruefung (Rollen-Matrix) */
    protected static ?string $permissionKey = 'partner.employees';

    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Personal';

    protected static ?string $modelLabel = 'Mitarbeiter';

    protected static ?string $pluralModelLabel = 'Mitarbeiter';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'last_name';

    /**
     * Seitentitel: Name statt E-Mail anzeigen.
     */
    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record?->name ?? $record?->email;
    }

    // --- Autorisierung: laeuft komplett ueber HasCatalogPermissions ---
    // (canViewAny/canCreate/canEdit/canDelete kommen aus der Rollen-Matrix;
    //  Loeschen/Archivieren steuert die Permission partner.employees.delete)

    // --- Query: Nur Mitarbeiter des eigenen Mandanten ---

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->whereIn('type', ['employee', 'partner'])
            ->where('tenant_id', session('tenant_id'))
            ->with(['employeeProfile', 'gasStations', 'documents', 'bankAccounts']);
    }

    /**
     * Deutsche IBAN aus BLZ + Kontonummer berechnen.
     */
    public static function calculateGermanIbanPublic(string $blz, string $kontonummer): ?string
    {
        return static::calculateGermanIban($blz, $kontonummer);
    }

    public static function lookupBankDataPublic(string $iban): array
    {
        return static::lookupBankData($iban);
    }

    protected static function calculateGermanIban(string $blz, string $kontonummer): ?string
    {
        $blz = preg_replace('/\s+/', '', $blz);
        $kontonummer = preg_replace('/\s+/', '', $kontonummer);

        if (strlen($blz) !== 8 || ! ctype_digit($blz)) {
            return null;
        }
        if (! ctype_digit($kontonummer) || strlen($kontonummer) > 10 || strlen($kontonummer) < 1) {
            return null;
        }

        $kontonummer = str_pad($kontonummer, 10, '0', STR_PAD_LEFT);
        $bban = $blz . $kontonummer;

        // DE = 13 14, Pruefziffer-Platzhalter 00
        $numericString = $bban . '131400';
        $remainder = bcmod($numericString, '97');
        $checkDigits = str_pad((string) (98 - (int) $remainder), 2, '0', STR_PAD_LEFT);

        return 'DE' . $checkDigits . $bban;
    }

    /**
     * BIC und Bankname anhand der IBAN ueber OpenIBAN API ermitteln.
     * Kostenlos, kein API-Key noetig.
     *
     * @return array{bic: string|null, bankName: string|null}
     */
    protected static function lookupBankData(string $iban): array
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->get("https://openiban.com/validate/{$iban}", [
                    'getBIC' => 'true',
                    'validateBankCode' => 'true',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $bankData = $data['bankData'] ?? [];

                return [
                    'bic' => $bankData['bic'] ?? null,
                    'bankName' => $bankData['name'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            // API nicht erreichbar — stille Fehlerbehandlung
        }

        return ['bic' => null, 'bankName' => null];
    }

    // --- Formular (10 Tabs) ---

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Mitarbeiter')
                ->tabs([
                    // =============================================
                    // Tab 1: Stammdaten
                    // =============================================
                    Tab::make(__('partner.employee.tabs.stammdaten'))
                        ->icon('heroicon-o-user')
                        ->schema([
                            TextInput::make('first_name')
                                ->label(__('partner.employee.fields.first_name'))
                                ->maxLength(255),
                            TextInput::make('last_name')
                                ->label(__('partner.employee.fields.last_name'))
                                ->required()
                                ->maxLength(255),
                            TextInput::make('email')
                                ->label(__('partner.employee.fields.email'))
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                            TextInput::make('phone')
                                ->label(__('partner.employee.fields.phone'))
                                ->tel()
                                ->maxLength(50),

                            TextInput::make('scan_code')
                                ->label('Scan-Code (POS Login)')
                                ->helperText('Eindeutiger Code fuer Login per Scanner/NFC/QR-Code an der POS-App.')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255)
                                ->placeholder('z.B. Badge-Nummer oder NFC-UID')
                                ->suffixAction(
                                    \Filament\Actions\Action::make('generate_scan_code')
                                        ->icon('heroicon-o-arrow-path')
                                        ->tooltip('Zufaelligen Code generieren')
                                        ->action(function (Set $set) {
                                            $set('scan_code', strtoupper(Str::random(12)));
                                        })
                                ),

                            TextInput::make('pin_hash')
                                ->label('PIN (POS Login)')
                                ->helperText(fn (string $operation) => $operation === 'create'
                                    ? '4-stellige PIN fuer den Login an der POS-App.'
                                    : 'Leer lassen um bestehende PIN zu behalten.')
                                ->required(fn (string $operation) => $operation === 'create')
                                ->minLength(4)
                                ->maxLength(6)
                                ->placeholder(fn (string $operation) => $operation === 'create' ? 'z.B. 1234' : 'Neue PIN eingeben...')
                                ->password()
                                ->revealable()
                                ->autocomplete('new-password')
                                ->formatStateUsing(fn () => null)
                                ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                                ->dehydrated(fn (?string $state) => filled($state)),

                            Select::make('employeeProfile.gender')
                                ->label(__('partner.employee.fields.gender'))
                                ->options(__('partner.employee.genders')),
                            DatePicker::make('employeeProfile.date_of_birth')
                                ->label(__('partner.employee.fields.date_of_birth'))
                                ->native(true)
                                ->maxDate(now()->subYears(14))
                                ->placeholder('TT.MM.JJJJ'),
                            TextInput::make('employeeProfile.place_of_birth')
                                ->label(__('partner.employee.fields.place_of_birth'))
                                ->maxLength(255),
                            Select::make('employeeProfile.nationality')
                                ->label(__('partner.employee.fields.nationality'))
                                ->options(fn () => static::getNationalityOptions())
                                ->searchable()
                                ->rule('in:' . implode(',', array_keys(static::getNationalityOptions())))
                                ->validationMessages([
                                    'in' => 'Bitte wählen Sie eine gültige Staatsangehörigkeit aus der Liste.',
                                ])
                                ->createOptionForm([
                                    TextInput::make('nationality')
                                        ->label('Neue Staatsangehörigkeit')
                                        ->required()
                                        ->maxLength(100)
                                        ->regex('/^[\pL\s\-]+$/u')
                                        ->validationMessages([
                                            'regex' => 'Nur Buchstaben, Leerzeichen und Bindestriche erlaubt.',
                                        ]),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    $value = trim($data['nationality']);
                                    static::addCustomNationality($value);
                                    return $value;
                                })
                                ->createOptionAction(fn ($action) => $action->label('Andere')),
                            Select::make('employeeProfile.second_nationality')
                                ->label(__('partner.employee.fields.second_nationality'))
                                ->options(fn () => static::getNationalityOptions())
                                ->searchable()
                                ->rule('in:' . implode(',', array_keys(static::getNationalityOptions())))
                                ->validationMessages([
                                    'in' => 'Bitte wählen Sie eine gültige Staatsangehörigkeit aus der Liste.',
                                ])
                                ->createOptionForm([
                                    TextInput::make('nationality')
                                        ->label('Neue Staatsangehörigkeit')
                                        ->required()
                                        ->maxLength(100)
                                        ->regex('/^[\pL\s\-]+$/u')
                                        ->validationMessages([
                                            'regex' => 'Nur Buchstaben, Leerzeichen und Bindestriche erlaubt.',
                                        ]),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    $value = trim($data['nationality']);
                                    static::addCustomNationality($value);
                                    return $value;
                                })
                                ->createOptionAction(fn ($action) => $action->label('Andere'))
                                ->hintAction(
                                    \Filament\Actions\Action::make('manage_nationalities')
                                        ->label('Eigene verwalten')
                                        ->icon('heroicon-o-trash')
                                        ->color('danger')
                                        ->size('sm')
                                        ->form(fn () => [
                                            CheckboxList::make('delete_nationalities')
                                                ->label('Zum Löschen auswählen')
                                                ->options(function () {
                                                    $tenant = auth()->user()?->tenant;
                                                    $custom = (array) data_get($tenant?->settings, 'custom_nationalities', []);
                                                    return collect($custom)->mapWithKeys(fn ($v) => [$v => $v])->toArray();
                                                })
                                                ->helperText('Markierte Einträge werden aus der Auswahlliste entfernt.')
                                                ->columns(2),
                                        ])
                                        ->modalHeading('Benutzerdefinierte Staatsangehörigkeiten')
                                        ->modalSubmitActionLabel('Ausgewählte löschen')
                                        ->modalWidth('md')
                                        ->action(function (array $data) {
                                            $toDelete = $data['delete_nationalities'] ?? [];
                                            if (empty($toDelete)) return;

                                            $tenant = auth()->user()->tenant;
                                            $settings = $tenant->settings ?? [];
                                            $custom = (array) data_get($settings, 'custom_nationalities', []);
                                            $custom = array_values(array_diff($custom, $toDelete));
                                            data_set($settings, 'custom_nationalities', $custom);
                                            $tenant->update(['settings' => $settings]);

                                            \Filament\Notifications\Notification::make()
                                                ->title(count($toDelete) . ' Einträge gelöscht')
                                                ->success()
                                                ->send();
                                        })
                                        ->visible(function () {
                                            $tenant = auth()->user()?->tenant;
                                            $custom = (array) data_get($tenant?->settings, 'custom_nationalities', []);
                                            return count($custom) > 0;
                                        })
                                ),
                            Select::make('employeeProfile.marital_status')
                                ->label(__('partner.employee.fields.marital_status'))
                                ->options(__('partner.employee.marital_statuses')),
                            Repeater::make('_gas_station_assignments')
                                ->label('Tankstellen-Zuordnung')
                                ->schema([
                                    Select::make('gas_station_id')
                                        ->label('Tankstelle')
                                        ->options(function (Get $get) {
                                            $allStations = \App\Models\GasStation::where('tenant_id', session('tenant_id'))
                                                ->orderBy('name')
                                                ->pluck('name', 'id');
                                            // Bereits gewaehlte Stationen aus anderen Zeilen ausfiltern
                                            $currentId = $get('gas_station_id');
                                            $items = $get('../../_gas_station_assignments') ?? [];
                                            $usedIds = collect($items)
                                                ->pluck('gas_station_id')
                                                ->filter(fn ($id) => $id && $id !== $currentId)
                                                ->toArray();
                                            return $allStations->except($usedIds);
                                        })
                                        ->required()
                                        ->searchable()
                                        ->live()
                                        ->placeholder('Tankstelle waehlen...'),
                                    Select::make('station_role')
                                        ->label('Rolle')
                                        ->options([
                                            'partner' => 'Partner/Inhaber',
                                            'manager' => 'Betriebsleiter',
                                            'station_manager' => 'Stationsleiter',
                                            'shift_leader' => 'Schichtleiter',
                                            'cashier' => 'Kassierer',
                                            'attendant' => 'Tankwart',
                                            'trainee' => 'Auszubildende/r',
                                            'cleaner' => 'Reinigungskraft',
                                            'carwash' => 'Waschanlage',
                                            'employee' => 'Mitarbeiter',
                                        ])
                                        ->placeholder('Rolle waehlen...'),
                                    Toggle::make('is_primary')
                                        ->label('Hauptstandort')
                                        ->inline(false)
                                        ->live()
                                        ->afterStateUpdated(function (bool $state, Set $set, Get $get) {
                                            if (! $state) return;
                                            // Alle anderen Eintraege auf false setzen
                                            $items = $get('../../_gas_station_assignments');
                                            if (! is_array($items)) return;
                                            foreach ($items as $key => $item) {
                                                if (($item['gas_station_id'] ?? null) !== $get('gas_station_id')) {
                                                    $set("../../_gas_station_assignments.{$key}.is_primary", false);
                                                }
                                            }
                                        }),
                                ])
                                ->columns(3)
                                ->defaultItems(0)
                                ->maxItems(fn () => \App\Models\GasStation::where('tenant_id', session('tenant_id'))->count())
                                ->addActionLabel('Tankstelle hinzufuegen')
                                ->reorderable(false)
                                ->itemLabel(function (array $state): ?string {
                                    $name = $state['gas_station_id']
                                        ? \App\Models\GasStation::find($state['gas_station_id'])?->name
                                        : null;
                                    return $name ?: 'Neue Zuordnung';
                                })
                                ->collapsible()
                                ->dehydrated(false)
                                ->columnSpanFull(),
                            Toggle::make('is_active')
                                ->label(__('partner.employee.fields.is_active'))
                                ->columnSpan(2),
                        ])
                        ->columns(2),

                    // =============================================
                    // Tab 2: Adresse & Kontakt
                    // =============================================
                    Tab::make(__('partner.employee.tabs.adresse'))
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            TextInput::make('employeeProfile.street')
                                ->label(__('partner.employee.fields.street'))
                                ->maxLength(255),
                            TextInput::make('employeeProfile.zip')
                                ->label(__('partner.employee.fields.zip'))
                                ->maxLength(10),
                            TextInput::make('employeeProfile.city')
                                ->label(__('partner.employee.fields.city'))
                                ->maxLength(255),
                            TextInput::make('employeeProfile.country')
                                ->label(__('partner.employee.fields.country'))
                                ->maxLength(100)
                                ->default('Deutschland'),

                            Section::make('Notfallkontakt')
                                ->icon('heroicon-o-phone')
                                ->schema([
                                    TextInput::make('employeeProfile.emergency_name')
                                        ->label(__('partner.employee.fields.emergency_name'))
                                        ->maxLength(255),
                                    TextInput::make('employeeProfile.emergency_phone')
                                        ->label(__('partner.employee.fields.emergency_phone'))
                                        ->tel()
                                        ->maxLength(50),
                                    TextInput::make('employeeProfile.emergency_relation')
                                        ->label(__('partner.employee.fields.emergency_relation'))
                                        ->maxLength(100),
                                ])
                                ->columns(3)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    // =============================================
                    // Tab 3: Beschaeftigung
                    // =============================================
                    Tab::make(__('partner.employee.tabs.beschaeftigung'))
                        ->icon('heroicon-o-briefcase')
                        ->schema([
                            Select::make('employeeProfile.employment_type')
                                ->label(__('partner.employee.fields.employment_type'))
                                ->options(__('partner.employee.employment_types')),
                            TextInput::make('employeeProfile.weekly_hours')
                                ->label(__('partner.employee.fields.weekly_hours'))
                                ->numeric()
                                ->step(0.5)
                                ->minValue(0)
                                ->maxValue(48)
                                ->suffix('Std.'),
                            DatePicker::make('employeeProfile.employment_start')
                                ->label(__('partner.employee.fields.employment_start'))
                                ->displayFormat('d.m.Y'),
                            DatePicker::make('employeeProfile.employment_end')
                                ->label(__('partner.employee.fields.employment_end'))
                                ->displayFormat('d.m.Y'),
                            DatePicker::make('employeeProfile.probation_end')
                                ->label(__('partner.employee.fields.probation_end'))
                                ->displayFormat('d.m.Y'),
                            TextInput::make('employeeProfile.vacation_days')
                                ->label(__('partner.employee.fields.vacation_days'))
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(60)
                                ->suffix('Tage'),
                            Select::make('employeeProfile.salary_type')
                                ->label(__('partner.employee.fields.salary_type'))
                                ->options(__('partner.employee.salary_types'))
                                ->live(),
                            TextInput::make('employeeProfile.salary')
                                ->label(fn (Get $get) => match ($get('employeeProfile.salary_type')) {
                                    'hourly' => 'Stundenlohn',
                                    default => 'Festgehalt (Brutto)',
                                })
                                ->numeric()
                                ->prefix('EUR')
                                ->suffix(fn (Get $get) => $get('employeeProfile.salary_type') === 'hourly' ? '/Std.' : '/Monat')
                                ->step(0.01),
                        ])
                        ->columns(2),

                    // =============================================
                    // Tab 4: Steuer & Sozialversicherung
                    // =============================================
                    Tab::make(__('partner.employee.tabs.steuer_sv'))
                        ->icon('heroicon-o-building-library')
                        ->schema([
                            TextInput::make('employeeProfile.tax_id')
                                ->label(__('partner.employee.fields.tax_id'))
                                ->maxLength(20)
                                ->placeholder('z.B. 12 345 678 901'),
                            Select::make('employeeProfile.tax_class')
                                ->label(__('partner.employee.fields.tax_class'))
                                ->options(__('partner.employee.tax_classes')),
                            Toggle::make('employeeProfile.church_tax')
                                ->label(__('partner.employee.fields.church_tax'))
                                ->live(),
                            Select::make('employeeProfile.denomination')
                                ->label(__('partner.employee.fields.denomination'))
                                ->options([
                                    'rk' => 'Roemisch-Katholisch',
                                    'ev' => 'Evangelisch',
                                    'ak' => 'Altkatholisch',
                                    'fr' => 'Franzoesisch-Reformiert',
                                    'fb' => 'Freireligioese Gemeinde',
                                    'jd' => 'Juedisch',
                                    'isl' => 'Islamisch',
                                    'none' => 'Konfessionslos',
                                    'other' => 'Sonstige',
                                ])
                                ->searchable()
                                ->visible(fn (Get $get) => (bool) $get('employeeProfile.church_tax')),
                            TextInput::make('employeeProfile.social_security_number')
                                ->label(__('partner.employee.fields.social_security_number'))
                                ->maxLength(20)
                                ->placeholder('z.B. 12 010190 A 123'),
                            Select::make('employeeProfile.health_insurance_name')
                                ->label(__('partner.employee.fields.health_insurance_name'))
                                ->options(fn () => \App\Models\HealthInsurance::forTenant()
                                    ->orderBy('sort_order')->orderBy('name')
                                    ->pluck('name', 'name')
                                    ->toArray())
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->label('Krankenkassen-Name')
                                        ->required()
                                        ->maxLength(255),
                                    Select::make('type')
                                        ->label('Art')
                                        ->options([
                                            'gesetzlich' => 'Gesetzlich',
                                            'privat' => 'Privat',
                                        ])
                                        ->default('gesetzlich')
                                        ->required(),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    \App\Models\HealthInsurance::create([
                                        'tenant_id' => session('tenant_id'),
                                        'name' => trim($data['name']),
                                        'type' => $data['type'] ?? 'gesetzlich',
                                        'is_system' => false,
                                        'sort_order' => 100,
                                    ]);
                                    return trim($data['name']);
                                })
                                ->createOptionAction(fn ($action) => $action->label('Neue Kasse'))
                                ->placeholder('Krankenkasse waehlen'),
                            Select::make('employeeProfile.health_insurance_type')
                                ->label(__('partner.employee.fields.health_insurance_type'))
                                ->options(__('partner.employee.insurance_types')),
                            TextInput::make('employeeProfile.health_insurance_number')
                                ->label(__('partner.employee.fields.health_insurance_number'))
                                ->maxLength(30),
                            Toggle::make('employeeProfile.pension_insurance')
                                ->label(__('partner.employee.fields.pension_insurance'))
                                ->columnSpan(2),
                        ])
                        ->columns(2),

                    // =============================================
                    // Tab 5: Bankverbindung
                    // =============================================
                    Tab::make(__('partner.employee.tabs.bankverbindung'))
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Repeater::make('_bank_accounts')
                                ->label('Bankkonten')
                                ->schema([
                                    Select::make('type')
                                        ->label('Verwendungszweck')
                                        ->options(fn () => static::getBankAccountTypeOptions())
                                        ->required()
                                        ->searchable()
                                        ->createOptionForm([
                                            TextInput::make('label')
                                                ->label('Neuer Verwendungszweck')
                                                ->required()
                                                ->maxLength(100),
                                        ])
                                        ->createOptionUsing(function (array $data) {
                                            $value = trim($data['label']);
                                            $tenant = auth()->user()->tenant;
                                            $settings = $tenant->settings ?? [];
                                            $custom = (array) data_get($settings, 'custom_bank_account_types', []);
                                            if (! in_array($value, $custom)) {
                                                $custom[] = $value;
                                                sort($custom);
                                                data_set($settings, 'custom_bank_account_types', $custom);
                                                $tenant->update(['settings' => $settings]);
                                            }
                                            return $value;
                                        })
                                        ->createOptionAction(fn ($action) => $action->label('Andere')),
                                    TextInput::make('account_holder')
                                        ->label('Kontoinhaber')
                                        ->maxLength(255),
                                    TextInput::make('iban')
                                        ->label('IBAN')
                                        ->required()
                                        ->maxLength(34)
                                        ->placeholder('IBAN eingeben oder Rechner nutzen →')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Set $set, ?string $state) {
                                            if ($state && strlen(preg_replace('/\s+/', '', $state)) >= 15) {
                                                $iban = preg_replace('/\s+/', '', $state);
                                                $bankData = static::lookupBankData($iban);
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
                                                    $iban = static::calculateGermanIban($data['blz'], $data['kontonummer']);
                                                    if (! $iban) {
                                                        \Filament\Notifications\Notification::make()
                                                            ->title('Ungueltige BLZ oder Kontonummer')
                                                            ->danger()
                                                            ->send();
                                                        return;
                                                    }
                                                    $set('iban', $iban);
                                                    $bankData = static::lookupBankData($iban);
                                                    if ($bankData['bic']) $set('bic', $bankData['bic']);
                                                    if ($bankData['bankName']) $set('bank_name', $bankData['bankName']);

                                                    \Filament\Notifications\Notification::make()
                                                        ->title('IBAN berechnet')
                                                        ->body($iban . ($bankData['bankName'] ? ' — ' . $bankData['bankName'] : ''))
                                                        ->success()
                                                        ->send();
                                                })
                                        )
                                        ->columnSpan(2),
                                    TextInput::make('bic')
                                        ->label('BIC')
                                        ->maxLength(11)
                                        ->placeholder('Wird automatisch ermittelt'),
                                    TextInput::make('bank_name')
                                        ->label('Bank')
                                        ->maxLength(255)
                                        ->placeholder('Wird automatisch ermittelt'),
                                ])
                                ->columns(3)
                                ->columnSpanFull()
                                ->dehydrated(false)
                                ->addActionLabel('Konto hinzufuegen')
                                ->defaultItems(0)
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string =>
                                    ($state['type'] ?? 'Konto') . (! empty($state['bank_name']) ? ' — ' . $state['bank_name'] : '')
                                )
                                ->helperText('Bankdaten werden verschluesselt gespeichert (AES-256).'),
                        ]),

                    // =============================================
                    // Tab 6: Qualifikationen & Schulungen
                    // =============================================
                    Tab::make(__('partner.employee.tabs.qualifikationen'))
                        ->icon('heroicon-o-academic-cap')
                        ->schema([
                            Select::make('employeeProfile.education_level')
                                ->label(__('partner.employee.fields.education_level'))
                                ->options(__('partner.employee.education_levels')),
                            TextInput::make('employeeProfile.professional_training')
                                ->label(__('partner.employee.fields.professional_training'))
                                ->maxLength(255)
                                ->placeholder('z.B. Kauffrau im Einzelhandel'),
                            Toggle::make('_has_drivers_license')
                                ->label('Führerschein vorhanden')
                                ->live()
                                ->dehydrated(false)
                                ->afterStateHydrated(function (Set $set, $record) {
                                    $license = $record?->employeeProfile?->drivers_license;
                                    $set('_has_drivers_license', ! empty($license));
                                })
                                ->columnSpanFull(),
                            CheckboxList::make('employeeProfile.drivers_license')
                                ->label(__('partner.employee.fields.drivers_license'))
                                ->options([
                                    'B' => 'B', 'BE' => 'BE', 'C' => 'C', 'CE' => 'CE',
                                    'C1' => 'C1', 'C1E' => 'C1E', 'AM' => 'AM',
                                    'A1' => 'A1', 'A2' => 'A2', 'A' => 'A',
                                ])
                                ->columns(5)
                                ->columnSpanFull()
                                ->visible(fn (Get $get) => $get('_has_drivers_license')),
                            DatePicker::make('employeeProfile.drivers_license_expiry')
                                ->label(__('partner.employee.fields.drivers_license_expiry'))
                                ->native(true)
                                ->visible(fn (Get $get) => $get('_has_drivers_license')),

                            Section::make('Weitere Zertifikate & Schulungen')
                                ->description('HQM, ADR, Kassenschulung, etc.')
                                ->icon('heroicon-o-trophy')
                                ->schema([
                                    Repeater::make('employeeProfile.certifications')
                                        ->label('')
                                        ->schema([
                                            TextInput::make('name')
                                                ->label('Bezeichnung')
                                                ->required()
                                                ->placeholder('z.B. HQM, ADR-Schein, Kassenschulung'),
                                            DatePicker::make('date')
                                                ->label('Datum')
                                                ->displayFormat('d.m.Y'),
                                            DatePicker::make('expires')
                                                ->label('Gueltig bis')
                                                ->displayFormat('d.m.Y'),
                                            TextInput::make('notes')
                                                ->label('Bemerkungen')
                                                ->maxLength(255),
                                        ])
                                        ->columns(4)
                                        ->columnSpanFull()
                                        ->addActionLabel('Schulung/Zertifikat hinzufuegen')
                                        ->defaultItems(0)
                                        ->collapsible()
                                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                                ])
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    // =============================================
                    // Tab 7: Kinder & Familie
                    // =============================================
                    Tab::make(__('partner.employee.tabs.kinder'))
                        ->icon('heroicon-o-heart')
                        ->schema([
                            TextInput::make('employeeProfile.num_children')
                                ->label(__('partner.employee.fields.num_children'))
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(20),
                            Toggle::make('employeeProfile.child_allowance')
                                ->label(__('partner.employee.fields.child_allowance')),

                            Section::make('Kinder-Daten')
                                ->icon('heroicon-o-user-group')
                                ->schema([
                                    Repeater::make('employeeProfile.children_data')
                                        ->label('')
                                        ->schema([
                                            TextInput::make('name')
                                                ->label(__('partner.employee.fields.child_name'))
                                                ->required()
                                                ->maxLength(255),
                                            DatePicker::make('birth_date')
                                                ->label(__('partner.employee.fields.child_birth_date'))
                                                ->displayFormat('d.m.Y'),
                                            TextInput::make('tax_id')
                                                ->label('Steuer-ID Kind')
                                                ->maxLength(20),
                                        ])
                                        ->columns(3)
                                        ->columnSpanFull()
                                        ->addActionLabel('Kind hinzufuegen')
                                        ->defaultItems(0)
                                        ->collapsible()
                                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
                                ])
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    // =============================================
                    // Tab 8: Schulungen & Nachweise
                    // =============================================
                    Tab::make('Schulungen & Nachweise')
                        ->icon('heroicon-o-shield-check')
                        ->schema([
                            Section::make('Pflichtschulungen')
                                ->description('Gesetzlich vorgeschriebene Schulungen und deren Termine.')
                                ->icon('heroicon-o-shield-check')
                                ->schema([
                                    DatePicker::make('employeeProfile.safety_training_date')
                                        ->label(__('partner.employee.fields.safety_training_date'))
                                        ->native(true),
                                    DatePicker::make('employeeProfile.first_aid_training_date')
                                        ->label(__('partner.employee.fields.first_aid_training_date'))
                                        ->native(true),
                                    DatePicker::make('employeeProfile.hazmat_training_date')
                                        ->label(__('partner.employee.fields.hazmat_training_date'))
                                        ->native(true),
                                ])
                                ->columns(3)
                                ->columnSpanFull(),

                            Section::make('Gesundheitszeugnis')
                                ->icon('heroicon-o-clipboard-document-check')
                                ->schema([
                                    Toggle::make('employeeProfile.health_certificate')
                                        ->label(__('partner.employee.fields.health_certificate')),
                                    DatePicker::make('employeeProfile.health_certificate_date')
                                        ->label(__('partner.employee.fields.health_certificate_date'))
                                        ->native(true),
                                ])
                                ->columns(2)
                                ->columnSpanFull(),

                            Section::make('Aufenthaltstitel')
                                ->icon('heroicon-o-identification')
                                ->schema([
                                    TextInput::make('employeeProfile.residence_permit_type')
                                        ->label(__('partner.employee.fields.residence_permit_type'))
                                        ->maxLength(100)
                                        ->placeholder('z.B. Aufenthaltserlaubnis, Niederlassungserlaubnis'),
                                    DatePicker::make('employeeProfile.residence_permit_expires')
                                        ->label(__('partner.employee.fields.residence_permit_expires'))
                                        ->native(true),
                                ])
                                ->columns(2)
                                ->columnSpanFull(),
                        ]),

                    // =============================================
                    // Tab 9: Dokumente
                    // =============================================
                    Tab::make(__('partner.employee.tabs.dokumente'))
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            // --- Aktion: KI-Dokument erstellen ---
                            SchemaActions::make([
                                Actions\Action::make('create_document_from_template')
                                    ->label('Neues Dokument erstellen')
                                    ->icon('heroicon-o-sparkles')
                                    ->color('primary')
                                    ->form([
                                        Select::make('document_type')
                                            ->label('Was moechten Sie erstellen?')
                                            ->options(\App\Services\AiTextService::getDocumentTypeOptions())
                                            ->required()
                                            ->searchable()
                                            ->live()
                                            ->native(false)
                                            ->placeholder('Dokumenttyp waehlen...'),
                                        Textarea::make('_ai_instructions')
                                            ->label('Zusaetzliche Angaben (optional)')
                                            ->placeholder('z.B. "befristet auf 1 Jahr", "betriebsbedingte Kuendigung", "Note sehr gut", "besondere Klausel fuer Sonntagsarbeit"...')
                                            ->rows(3)
                                            ->helperText('Je mehr Details Sie angeben, desto besser wird das Dokument.')
                                            ->visible(fn (Get $get) => ! empty($get('document_type')))
                                            ->dehydrated(false),
                                        Select::make('gas_station_id')
                                            ->label('Tankstelle')
                                            ->options(fn () => \App\Models\GasStation::query()
                                                ->where('tenant_id', session('tenant_id'))
                                                ->pluck('name', 'id'))
                                            ->searchable()
                                            ->placeholder('Optional — Tankstelle zuordnen')
                                            ->visible(fn (Get $get) => ! empty($get('document_type'))),

                                        // KI-Generieren Button
                                        \Filament\Schemas\Components\Actions::make([
                                            Actions\Action::make('generate_ai_document')
                                                ->label('Dokument mit KI generieren')
                                                ->icon('heroicon-o-sparkles')
                                                ->color('warning')
                                                ->size('lg')
                                                ->action(function (Get $get, Set $set, $record) {
                                                    $aiService = app(\App\Services\AiTextService::class);
                                                    $documentType = $get('document_type');

                                                    if (! $documentType) {
                                                        \Filament\Notifications\Notification::make()
                                                            ->title('Dokumenttyp waehlen')
                                                            ->body('Bitte waehlen Sie zuerst einen Dokumenttyp aus.')
                                                            ->warning()
                                                            ->send();
                                                        return;
                                                    }

                                                    if (! $record) {
                                                        \Filament\Notifications\Notification::make()
                                                            ->title('Fehler')
                                                            ->body('Mitarbeiter-Daten nicht verfuegbar.')
                                                            ->danger()
                                                            ->send();
                                                        return;
                                                    }

                                                    // Mitarbeiter-Daten laden
                                                    $record->load(['employeeProfile', 'gasStations', 'tenant']);

                                                    $instructions = $get('_ai_instructions');

                                                    try {
                                                        $generated = $aiService->generateFullDocument(
                                                            $documentType,
                                                            $record,
                                                            $instructions,
                                                        );
                                                        $set('content', $generated);

                                                        \Filament\Notifications\Notification::make()
                                                            ->title('Dokument generiert!')
                                                            ->body('Bitte pruefen Sie den Inhalt und passen Sie ihn bei Bedarf an.')
                                                            ->success()
                                                            ->send();
                                                    } catch (\Exception $e) {
                                                        \Filament\Notifications\Notification::make()
                                                            ->title('KI-Fehler')
                                                            ->body($e->getMessage())
                                                            ->danger()
                                                            ->send();
                                                    }
                                                }),
                                        ])
                                            ->visible(fn (Get $get) => ! empty($get('document_type')))
                                            ->columnSpanFull(),

                                        RichEditor::make('content')
                                            ->label('Dokumentinhalt')
                                            ->helperText('Von der KI generiert — Sie koennen den Inhalt hier frei bearbeiten.')
                                            ->toolbarButtons([
                                                'bold', 'italic', 'underline',
                                                'h2', 'h3',
                                                'bulletList', 'orderedList',
                                                'blockquote', 'link',
                                                'undo', 'redo',
                                            ])
                                            ->visible(fn (Get $get) => ! empty($get('content')))
                                            ->columnSpanFull(),

                                        // Ueberarbeiten-Button (nachdem Inhalt generiert wurde)
                                        Section::make('Dokument ueberarbeiten')
                                            ->icon('heroicon-o-pencil-square')
                                            ->collapsed()
                                            ->schema([
                                                Textarea::make('_refine_instructions')
                                                    ->label('Was soll geaendert werden?')
                                                    ->placeholder('z.B. "Probezeit auf 3 Monate aendern", "formeller formulieren", "Paragraph zu Ueberstunden ergaenzen"...')
                                                    ->rows(2)
                                                    ->dehydrated(false),
                                                \Filament\Schemas\Components\Actions::make([
                                                    Actions\Action::make('refine_ai_document')
                                                        ->label('Ueberarbeiten')
                                                        ->icon('heroicon-o-arrow-path')
                                                        ->color('gray')
                                                        ->action(function (Get $get, Set $set, $record) {
                                                            $aiService = app(\App\Services\AiTextService::class);
                                                            $instructions = $get('_refine_instructions');
                                                            $currentContent = $get('content');

                                                            if (! $instructions || ! $currentContent) {
                                                                \Filament\Notifications\Notification::make()
                                                                    ->title('Anweisungen fehlen')
                                                                    ->body('Bitte geben Sie an, was geaendert werden soll.')
                                                                    ->warning()
                                                                    ->send();
                                                                return;
                                                            }

                                                            $record->load(['employeeProfile', 'gasStations', 'tenant']);

                                                            try {
                                                                $refined = $aiService->refineDocument(
                                                                    $currentContent,
                                                                    $instructions,
                                                                    $record,
                                                                );
                                                                $set('content', $refined);
                                                                $set('_refine_instructions', '');

                                                                \Filament\Notifications\Notification::make()
                                                                    ->title('Dokument ueberarbeitet')
                                                                    ->success()
                                                                    ->send();
                                                            } catch (\Exception $e) {
                                                                \Filament\Notifications\Notification::make()
                                                                    ->title('KI-Fehler')
                                                                    ->body($e->getMessage())
                                                                    ->danger()
                                                                    ->send();
                                                            }
                                                        }),
                                                ]),
                                            ])
                                            ->visible(fn (Get $get) => ! empty($get('content')))
                                            ->columnSpanFull(),

                                        Toggle::make('generate_pdf')
                                            ->label('PDF sofort generieren')
                                            ->default(true)
                                            ->visible(fn (Get $get) => ! empty($get('content'))),
                                        Toggle::make('send_email')
                                            ->label('Direkt per E-Mail versenden (mit Signatur-Link)')
                                            ->default(false)
                                            ->visible(fn (Get $get) => ! empty($get('content'))),
                                    ])
                                    ->action(function (array $data, $record) {
                                        $documentType = $data['document_type'];
                                        $typeLabels = \App\Services\AiTextService::getDocumentTypeOptions();
                                        $title = $typeLabels[$documentType] ?? $documentType;

                                        $station = ! empty($data['gas_station_id'])
                                            ? \App\Models\GasStation::find($data['gas_station_id'])
                                            : null;

                                        // Dokument erstellen
                                        $document = Document::create([
                                            'tenant_id' => auth()->user()->tenant_id,
                                            'user_id' => $record->id,
                                            'gas_station_id' => $station?->id,
                                            'type' => $documentType,
                                            'category' => 'hr',
                                            'title' => $title . ' — ' . $record->name,
                                            'content' => $data['content'],
                                            'status' => 'draft',
                                            'version' => 1,
                                            'requires_signature' => true,
                                            'created_by' => auth()->id(),
                                        ]);

                                        $service = app(DocumentService::class);

                                        if (! empty($data['generate_pdf'])) {
                                            $service->generatePdf($document);
                                        }

                                        if (! empty($data['send_email'])) {
                                            $service->sendDocument($document, $record->email);
                                        }

                                        \Filament\Notifications\Notification::make()
                                            ->title('Dokument erstellt')
                                            ->body("\"{$document->title}\" wurde erfolgreich erstellt.")
                                            ->success()
                                            ->send();
                                    })
                                    ->modalHeading('Neues Dokument erstellen')
                                    ->modalDescription('Waehlen Sie den Dokumenttyp — die KI erstellt das komplette Dokument mit allen Mitarbeiterdaten.')
                                    ->modalSubmitActionLabel('Dokument speichern')
                                    ->modalWidth('5xl'),

                                // --- Onboarding-Paket: Alle Pflichtformulare auf einmal ---
                                Actions\Action::make('create_onboarding_package')
                                    ->label('Onboarding-Paket erstellen')
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->color('success')
                                    ->requiresConfirmation()
                                    ->modalHeading('Onboarding-Paket erstellen')
                                    ->modalDescription(fn ($record) => "Alle Pflicht-Belehrungen fuer {$record->name} werden generiert, als PDF gespeichert und per E-Mail zur Unterschrift verschickt.")
                                    ->modalSubmitActionLabel('Alle Dokumente erstellen & versenden')
                                    ->form([
                                        Select::make('_onboarding_station_id')
                                            ->label('Tankstelle')
                                            ->options(fn ($record) => $record->gasStations->pluck('name', 'id'))
                                            ->required()
                                            ->placeholder('Tankstelle waehlen...'),
                                        Placeholder::make('_onboarding_info')
                                            ->hiddenLabel()
                                            ->content(function () {
                                                $templates = DocumentTemplate::where('is_onboarding', true)
                                                    ->where('is_active', true)
                                                    ->forCurrentTenant()
                                                    ->get();

                                                $list = $templates->map(fn ($t) => '<li style="padding:4px 0;">' . e($t->name) . '</li>')->join('');

                                                return new \Illuminate\Support\HtmlString(
                                                    '<div style="padding:12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;">'
                                                    . '<p style="font-weight:600;margin-bottom:8px;">Folgende Pflichtformulare werden erstellt:</p>'
                                                    . '<ul style="margin:0;padding-left:20px;">' . $list . '</ul>'
                                                    . '</div>'
                                                );
                                            }),
                                    ])
                                    ->action(function (array $data, $record) {
                                        $templates = DocumentTemplate::where('is_onboarding', true)
                                            ->where('is_active', true)
                                            ->forCurrentTenant()
                                            ->get();

                                        if ($templates->isEmpty()) {
                                            \Filament\Notifications\Notification::make()
                                                ->title('Keine Onboarding-Vorlagen')
                                                ->body('Es sind keine aktiven Pflichtformulare konfiguriert.')
                                                ->warning()
                                                ->send();
                                            return;
                                        }

                                        $station = \App\Models\GasStation::find($data['_onboarding_station_id']);
                                        $docService = app(DocumentService::class);
                                        $created = 0;

                                        foreach ($templates as $template) {
                                            $variables = $docService->buildVariableMap($record, $station);
                                            $content = $docService->replacePlaceholders($template->content, $variables);

                                            $document = Document::create([
                                                'tenant_id' => auth()->user()->tenant_id,
                                                'user_id' => $record->id,
                                                'gas_station_id' => $station?->id,
                                                'template_id' => $template->id,
                                                'type' => $template->type,
                                                'category' => $template->category ?? 'onboarding',
                                                'title' => $template->name . ' — ' . $record->name,
                                                'content' => $content,
                                                'status' => 'draft',
                                                'version' => 1,
                                                'requires_signature' => true,
                                                'created_by' => auth()->id(),
                                            ]);

                                            // PDF generieren
                                            $docService->generatePdf($document);

                                            // Per E-Mail mit Signatur-Link versenden
                                            if ($record->email) {
                                                $docService->sendDocument($document, $record->email);
                                            }

                                            $created++;
                                        }

                                        \Filament\Notifications\Notification::make()
                                            ->title("Onboarding-Paket erstellt")
                                            ->body("{$created} Pflichtformular(e) wurden erstellt und per E-Mail an {$record->email} verschickt.")
                                            ->success()
                                            ->send();
                                    }),

                                Actions\Action::make('go_to_documents')
                                    ->label('Alle Dokumente verwalten')
                                    ->icon('heroicon-o-arrow-top-right-on-square')
                                    ->color('gray')
                                    ->url(fn () => DocumentResource::getUrl('index')),
                            ])->columnSpanFull(),

                            // --- Vorhandene Dokumente ---
                            Section::make('Vorhandene Dokumente')
                                ->icon('heroicon-o-document-text')
                                ->schema([
                                    Placeholder::make('documents_list')
                                        ->hiddenLabel()
                                        ->content(function ($record) {
                                            if (! $record?->documents?->count()) {
                                                return new \Illuminate\Support\HtmlString(
                                                    '<div style="text-align:center;padding:2rem 0;color:#9ca3af;">'
                                                    . '<p style="font-size:1.1rem;">Noch keine Dokumente vorhanden.</p>'
                                                    . '<p style="font-size:0.85rem;margin-top:4px;">Erstellen Sie ein Dokument ueber den Button oben.</p>'
                                                    . '</div>'
                                                );
                                            }

                                            $statusColors = [
                                                'draft' => 'background:#f3f4f6;color:#374151;',
                                                'pending_signature' => 'background:#fef3c7;color:#92400e;',
                                                'sent' => 'background:#dbeafe;color:#1e40af;',
                                                'signed' => 'background:#dcfce7;color:#166534;',
                                                'counter_signed' => 'background:#d1fae5;color:#065f46;',
                                                'revoked' => 'background:#fee2e2;color:#991b1b;',
                                                'archived' => 'background:#f3f4f6;color:#4b5563;',
                                            ];
                                            $statusLabels = [
                                                'draft' => 'Entwurf',
                                                'pending_signature' => 'Warte auf Unterschrift',
                                                'sent' => 'Versendet',
                                                'signed' => 'Unterschrieben',
                                                'counter_signed' => 'Gegengezeichnet',
                                                'revoked' => 'Widerrufen',
                                                'archived' => 'Archiviert',
                                            ];

                                            $html = '<div style="display:flex;flex-direction:column;gap:8px;">';
                                            foreach ($record->documents->sortByDesc('created_at') as $doc) {
                                                $color = $statusColors[$doc->status] ?? 'background:#f3f4f6;color:#374151;';
                                                $label = $statusLabels[$doc->status] ?? $doc->status;
                                                $date = $doc->created_at?->format('d.m.Y');
                                                $pdfBadge = $doc->file_path
                                                    ? '<span style="font-size:11px;color:#059669;font-weight:600;">PDF</span>'
                                                    : '';
                                                $editUrl = DocumentResource::getUrl('edit', ['record' => $doc->id]);

                                                $html .= '<a href="' . $editUrl . '" style="display:flex;align-items:center;justify-content:space-between;border:1px solid #e5e7eb;border-radius:8px;padding:12px 16px;text-decoration:none;transition:all .15s;background:#fff;" onmouseover="this.style.borderColor=\'#93c5fd\';this.style.background=\'#f9fafb\'" onmouseout="this.style.borderColor=\'#e5e7eb\';this.style.background=\'#fff\'">';
                                                $html .= '<div style="display:flex;align-items:center;gap:10px;">';
                                                $html .= '<span style="font-size:18px;">📄</span>';
                                                $html .= '<div>';
                                                $html .= '<p style="margin:0;font-weight:600;color:#111827;font-size:14px;">' . e($doc->title) . '</p>';
                                                $html .= '<p style="margin:2px 0 0;font-size:12px;color:#6b7280;">' . $date . '</p>';
                                                $html .= '</div>';
                                                $html .= '</div>';
                                                $html .= '<div style="display:flex;align-items:center;gap:8px;">';
                                                $html .= $pdfBadge;
                                                $html .= '<span style="border-radius:9999px;padding:2px 10px;font-size:11px;font-weight:500;' . $color . '">' . $label . '</span>';
                                                $html .= '</div>';
                                                $html .= '</a>';
                                            }
                                            $html .= '</div>';

                                            return new \Illuminate\Support\HtmlString($html);
                                        })
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull(),

                            // --- Dateien hochladen ---
                            Section::make('Dateien hochladen')
                                ->description('Hier koennen Sie Dateien direkt dem Mitarbeiter zuordnen (Scan, Kopie, etc.).')
                                ->icon('heroicon-o-arrow-up-tray')
                                ->collapsed()
                                ->schema([
                                    FileUpload::make('employeeProfile.residence_permit_file')
                                        ->label('Aufenthaltsgenehmigung / Arbeitserlaubnis')
                                        ->directory('employee-documents')
                                        ->acceptedFileTypes(['application/pdf', 'image/*'])
                                        ->maxSize(5120),
                                ])
                                ->columnSpanFull(),
                        ]),

                    // =============================================
                    // Tab 10: Kommunikation
                    // =============================================
                    Tab::make('Rollen & Rechte')
                        ->icon('heroicon-o-shield-check')
                        ->schema([
                            Section::make('Rollen pro Tankstelle')
                                ->description('Welche Rolle hat der Mitarbeiter wo? Ohne Tankstelle gilt die Rolle im ganzen Betrieb. Was die Rollen duerfen, wird unter Einstellungen → Rollen & Rechte festgelegt.')
                                ->schema([
                                    Repeater::make('stationRoles')
                                        ->relationship()
                                        ->label('Zuweisungen')
                                        ->schema([
                                            Select::make('gas_station_id')
                                                ->label('Tankstelle')
                                                ->options(fn () => \App\Models\GasStation::where('tenant_id', session('tenant_id'))->pluck('name', 'id'))
                                                ->placeholder('🏢 Ganzer Betrieb')
                                                ->nullable(),

                                            Select::make('role_id')
                                                ->label('Rolle')
                                                ->options(function () {
                                                    $labels = config('permission-katalog.system_rollen', []);

                                                    return \Spatie\Permission\Models\Role::where('tenant_id', session('tenant_id'))
                                                        ->where('name', '!=', 'partner') // Inhaber-Rolle nicht zuweisbar
                                                        ->orderByDesc('is_system')
                                                        ->orderBy('name')
                                                        ->get()
                                                        ->mapWithKeys(fn ($r) => [
                                                            $r->id => ($labels[$r->name] ?? ucfirst($r->name)) . ($r->is_system ? ' 🔒' : ' ✏️'),
                                                        ]);
                                                })
                                                ->required(),
                                        ])
                                        ->columns(2)
                                        ->addActionLabel('＋ Rolle zuweisen')
                                        ->defaultItems(0)
                                        ->helperText('Beispiel: Kassierer in Fulda + Schichtleiter in Petersberg = zwei Zeilen.'),
                                ]),
                        ]),

                    Tab::make('Kommunikation')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->schema([
                            // Telegram-Status
                            Section::make('Telegram')
                                ->description('Telegram-Verknuepfung des Mitarbeiters.')
                                ->schema([
                                    Placeholder::make('telegram_status')
                                        ->label('Status')
                                        ->content(function ($record) {
                                            if (! $record) return '-';
                                            $link = $record->telegramLink;
                                            if (! $link || ! $link->isLinked()) {
                                                return new \Illuminate\Support\HtmlString(
                                                    '<span style="color: #ef4444; font-weight: 600;">❌ Nicht verknuepft</span>'
                                                );
                                            }
                                            $username = $link->telegram_username ? " (@{$link->telegram_username})" : '';
                                            $linkedAt = $link->linked_at?->format('d.m.Y H:i') ?? '';
                                            return new \Illuminate\Support\HtmlString(
                                                '<span style="color: #22c55e; font-weight: 600;">✅ Verknuepft' . e($username) . '</span>'
                                                . '<br><span style="color: #9ca3af; font-size: 12px;">Seit: ' . e($linkedAt) . '</span>'
                                            );
                                        }),

                                    Placeholder::make('telegram_link_url')
                                        ->label('Verknuepfungs-Link')
                                        ->content(function ($record) {
                                            if (! $record) return '-';
                                            $tenant = auth()->user()->tenant;
                                            $botUsername = $tenant->telegram_bot_username;

                                            if (! $botUsername || ! $tenant->isTelegramEnabled()) {
                                                return new \Illuminate\Support\HtmlString(
                                                    '<span style="color: #9ca3af; font-size: 13px;">Telegram ist nicht konfiguriert. '
                                                    . '<a href="/dashboard/messaging-settings" style="color: #3b82f6; text-decoration: underline;">Einstellungen</a></span>'
                                                );
                                            }

                                            $link = $record->telegramLink;
                                            if ($link && $link->isLinked()) {
                                                return new \Illuminate\Support\HtmlString(
                                                    '<span style="color: #9ca3af; font-size: 13px;">Bereits verknuepft — kein Link noetig.</span>'
                                                );
                                            }

                                            // Link generieren/anzeigen
                                            if (! $link) {
                                                $link = \App\Models\TelegramLink::createForUser($record);
                                            } elseif (! $link->isTokenValid()) {
                                                $link->regenerateToken();
                                            }

                                            $telegramUrl = "https://t.me/{$botUsername}?start={$link->link_token}";
                                            return new \Illuminate\Support\HtmlString(
                                                '<a href="' . e($telegramUrl) . '" target="_blank" '
                                                . 'style="color: #3b82f6; text-decoration: underline; word-break: break-all; font-size: 13px;">'
                                                . e($telegramUrl) . '</a>'
                                                . '<br><span style="color: #9ca3af; font-size: 12px;">Gueltig bis: '
                                                . e($link->link_token_expires_at->format('d.m.Y H:i')) . '</span>'
                                            );
                                        })
                                        ->columnSpanFull(),
                                ])
                                ->columns(2)
                                ->collapsible(),

                            // Bevorzugter Kanal
                            Section::make('Praeferenzen')
                                ->schema([
                                    Select::make('employeeProfile.preferred_channel')
                                        ->label('Bevorzugter Kanal')
                                        ->options([
                                            'email' => 'E-Mail',
                                            'telegram' => 'Telegram',
                                            'whatsapp' => 'WhatsApp (spaeter)',
                                        ])
                                        ->default('email')
                                        ->helperText('Ueber welchen Kanal soll der Mitarbeiter bevorzugt benachrichtigt werden?'),

                                    Placeholder::make('communication_consent')
                                        ->label('DSGVO-Einwilligung')
                                        ->content(function ($record) {
                                            if (! $record?->employeeProfile?->communication_consent_at) {
                                                return new \Illuminate\Support\HtmlString(
                                                    '<span style="color: #f59e0b;">⚠️ Keine Einwilligung vorhanden</span>'
                                                );
                                            }
                                            $date = $record->employeeProfile->communication_consent_at->format('d.m.Y H:i');
                                            $ip = $record->employeeProfile->communication_consent_ip ?? '-';
                                            return new \Illuminate\Support\HtmlString(
                                                '<span style="color: #22c55e;">✅ Einwilligung erteilt am ' . e($date) . '</span>'
                                                . '<br><span style="color: #9ca3af; font-size: 12px;">IP: ' . e($ip) . '</span>'
                                            );
                                        }),
                                ])
                                ->columns(2)
                                ->collapsible(),
                        ]),

                    // =============================================
                    // Tab 11: Notizen & Status
                    // =============================================
                    Tab::make(__('partner.employee.tabs.notizen'))
                        ->icon('heroicon-o-pencil-square')
                        ->schema([
                            Select::make('employeeProfile.status')
                                ->label(__('partner.employee.fields.status'))
                                ->options(__('partner.employee.profile_statuses')),
                            Placeholder::make('profile_completed_at')
                                ->label(__('partner.employee.fields.completed_at'))
                                ->content(fn ($record) => $record?->employeeProfile?->completed_at
                                    ? $record->employeeProfile->completed_at->format('d.m.Y H:i')
                                    : '-'),
                            Textarea::make('employeeProfile.notes')
                                ->label(__('partner.employee.fields.notes'))
                                ->rows(5)
                                ->columnSpanFull()
                                ->placeholder('Interne Notizen zum Mitarbeiter...'),
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
                TextColumn::make('last_name')
                    ->label(__('partner.employee.fields.name'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->name)
                    ->weight('bold'),

                BadgeColumn::make('type')
                    ->label('Typ')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'partner' => 'Partner / Inhaber',
                        default => 'Mitarbeiter',
                    })
                    ->colors([
                        'warning' => 'partner',
                        'gray' => 'employee',
                    ])
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('partner.employee.fields.email'))
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('employeeProfile.employment_type')
                    ->label(__('partner.employee.fields.employment_type'))
                    ->formatStateUsing(fn (?string $state) => $state
                        ? (__("partner.employee.employment_types.{$state}") ?? $state)
                        : '-')
                    ->colors([
                        'primary' => 'full_time',
                        'info' => 'part_time',
                        'warning' => 'mini_job',
                        'success' => 'trainee',
                        'gray' => 'intern',
                    ])
                    ->placeholder('-'),

                BadgeColumn::make('employeeProfile.status')
                    ->label(__('partner.employee.fields.status'))
                    ->formatStateUsing(fn (?string $state) => $state
                        ? (__("partner.employee.profile_statuses.{$state}") ?? $state)
                        : '-')
                    ->colors([
                        'warning' => 'pending',
                        'danger' => 'incomplete',
                        'success' => 'complete',
                        'gray' => 'archived',
                    ])
                    ->placeholder('-'),

                TextColumn::make('employeeProfile.employment_start')
                    ->label(__('partner.employee.fields.employment_start'))
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('-'),

                BooleanColumn::make('is_active')
                    ->label(__('partner.employee.fields.is_active'))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('partner.employee.fields.created_at'))
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('last_name', 'asc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('partner.employee.fields.is_active'))
                    ->trueLabel('Nur aktive')
                    ->falseLabel('Nur inaktive'),
                TrashedFilter::make()
                    ->label('Archiviert')
                    ->visible(fn () => auth()->user()?->type === 'partner'),
            ])
            ->actions([
                // ── POS Login QR-Code anzeigen/generieren ──
                Actions\Action::make('pos_login_qr')
                    ->label('POS Login-Code')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->modalHeading(fn ($record) => 'POS Login-Code: ' . $record->name)
                    ->modalContent(function ($record) {
                        $scanCode = $record->scan_code;

                        if (! $scanCode) {
                            return new HtmlString(
                                '<div style="text-align:center;padding:24px">'
                                . '<p style="color:#999;margin-bottom:12px">Kein Scan-Code hinterlegt.</p>'
                                . '<p style="font-size:13px;color:#666">Bitte zuerst im Mitarbeiter-Profil unter "Stammdaten" einen Scan-Code generieren.</p>'
                                . '</div>'
                            );
                        }

                        $qrSvg = QrCode::size(250)->margin(2)->generate($scanCode);
                        $qrBase64 = base64_encode($qrSvg);

                        return new HtmlString(
                            '<div style="text-align:center;padding:8px">'
                            // Info
                            . '<div style="margin-bottom:16px;font-size:13px;color:#666">'
                            . 'Diesen QR-Code auf den Mitarbeiter-Ausweis drucken oder als Badge verwenden.'
                            . '</div>'
                            // QR Code
                            . '<div style="display:inline-block;background:white;padding:16px;border:2px solid #e5e7eb;border-radius:12px;margin-bottom:16px">'
                            . '<img src="data:image/svg+xml;base64,' . $qrBase64 . '" style="width:250px;height:250px" />'
                            . '</div>'
                            // Name
                            . '<div style="font-size:18px;font-weight:bold;margin-bottom:4px">'
                            . e($record->name)
                            . '</div>'
                            // Code zum Kopieren
                            . '<div style="margin-top:12px;padding:8px 12px;background:#f5f5f5;border-radius:6px;font-family:monospace;font-size:14px;letter-spacing:2px;user-select:all">'
                            . e($scanCode)
                            . '</div>'
                            . '<div style="margin-top:8px;font-size:11px;color:#999">Code antippen zum Kopieren</div>'
                            . '</div>'
                        );
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Schliessen')
                    ->extraModalFooterActions(fn ($record) => [
                        Actions\Action::make('generate_and_show')
                            ->label($record->scan_code ? 'Neuen Code generieren' : 'Code generieren')
                            ->icon('heroicon-o-arrow-path')
                            ->color($record->scan_code ? 'warning' : 'success')
                            ->requiresConfirmation($record->scan_code ? true : false)
                            ->modalDescription($record->scan_code ? 'Der bisherige Code wird ungueltig. Der Mitarbeiter muss den neuen Code verwenden.' : null)
                            ->action(function () use ($record) {
                                $newCode = strtoupper(Str::random(12));
                                $record->update(['scan_code' => $newCode]);

                                \Filament\Notifications\Notification::make()
                                    ->title('Neuer Scan-Code generiert')
                                    ->body("Code fuer {$record->name}: {$newCode}")
                                    ->success()
                                    ->send();
                            }),
                        Actions\Action::make('print_badge')
                            ->label('Ausweis drucken')
                            ->icon('heroicon-o-printer')
                            ->color('gray')
                            ->visible((bool) $record->scan_code)
                            ->url(fn () => route('employee.badge', $record))
                            ->openUrlInNewTab(),
                    ]),

                // ── Ausweis als PDF drucken ──
                Actions\Action::make('print_badge_direct')
                    ->label('Ausweis')
                    ->icon('heroicon-o-identification')
                    ->color('gray')
                    ->tooltip('Mitarbeiter-Ausweis als PDF drucken')
                    ->visible(fn ($record) => (bool) $record->scan_code)
                    ->url(fn ($record) => route('employee.badge', $record))
                    ->openUrlInNewTab(),

                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\Action::make('delete_employee')
                    ->label('Archivieren')
                    ->icon('heroicon-o-archive-box')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Mitarbeiter archivieren')
                    ->modalDescription(fn ($record) => "Der Mitarbeiter \"{$record->name}\" wird archiviert und ist nicht mehr sichtbar. Die Daten bleiben erhalten und koennen bei Bedarf wiederhergestellt werden.")
                    ->modalSubmitActionLabel('Archivieren')
                    ->action(function ($record) {
                        $name = $record->name;
                        $record->update(['is_active' => false]);
                        $record->delete(); // Soft-Delete

                        \Filament\Notifications\Notification::make()
                            ->title('Mitarbeiter archiviert')
                            ->body("\"{$name}\" wurde archiviert.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => ! $record->trashed() && static::canDelete($record)),
                Actions\Action::make('restore_employee')
                    ->label('Wiederherstellen')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mitarbeiter wiederherstellen')
                    ->modalDescription(fn ($record) => "Der Mitarbeiter \"{$record->name}\" wird wiederhergestellt und ist wieder sichtbar.")
                    ->action(function ($record) {
                        $record->restore();
                        $record->update(['is_active' => true]);

                        \Filament\Notifications\Notification::make()
                            ->title('Mitarbeiter wiederhergestellt')
                            ->body("\"{$record->name}\" ist wieder aktiv.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => $record->trashed() && static::canRestore($record)),
            ]);
    }

    // --- Seiten ---

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'view' => Pages\ViewEmployee::route('/{record}'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }

    // --- Hilfsmethoden ---

    /**
     * Staatsangehoerigkeiten: Vordefinierte Liste + benutzerdefinierte aus Tenant-Settings.
     * Benutzerdefinierte Werte werden in tenant.settings.custom_nationalities gespeichert.
     */
    public static function getNationalityOptions(): array
    {
        $predefined = [
            'Deutsch' => 'Deutsch',
            'Türkisch' => 'Türkisch',
            'Polnisch' => 'Polnisch',
            'Italienisch' => 'Italienisch',
            'Rumänisch' => 'Rumänisch',
            'Kroatisch' => 'Kroatisch',
            'Griechisch' => 'Griechisch',
            'Bulgarisch' => 'Bulgarisch',
            'Syrisch' => 'Syrisch',
            'Afghanisch' => 'Afghanisch',
            'Irakisch' => 'Irakisch',
            'Russisch' => 'Russisch',
            'Ukrainisch' => 'Ukrainisch',
            'Serbisch' => 'Serbisch',
            'Bosnisch' => 'Bosnisch',
            'Kosovarisch' => 'Kosovarisch',
            'Ungarisch' => 'Ungarisch',
            'Portugiesisch' => 'Portugiesisch',
            'Spanisch' => 'Spanisch',
            'Französisch' => 'Französisch',
            'Niederländisch' => 'Niederländisch',
            'Österreichisch' => 'Österreichisch',
            'Schweizerisch' => 'Schweizerisch',
            'Amerikanisch' => 'Amerikanisch',
            'Britisch' => 'Britisch',
            'Indisch' => 'Indisch',
            'Chinesisch' => 'Chinesisch',
            'Vietnamesisch' => 'Vietnamesisch',
            'Thailändisch' => 'Thailändisch',
            'Eritreisch' => 'Eritreisch',
            'Somalisch' => 'Somalisch',
            'Iranisch' => 'Iranisch',
            'Marokkanisch' => 'Marokkanisch',
            'Tunesisch' => 'Tunesisch',
            'Staatenlos' => 'Staatenlos',
        ];

        // Benutzerdefinierte aus Tenant-Settings laden
        $tenant = auth()->user()?->tenant;
        $custom = $tenant ? (array) data_get($tenant->settings, 'custom_nationalities', []) : [];
        $customOptions = collect($custom)->mapWithKeys(fn ($val) => [$val => $val])->toArray();

        return array_merge($predefined, $customOptions);
    }

    /**
     * Kontotypen: Vordefinierte + benutzerdefinierte aus Tenant-Settings.
     */
    public static function getBankAccountTypeOptions(): array
    {
        $predefined = [
            'Gehalt' => 'Gehalt',
            'VWL' => 'VWL (Vermoegenswirksame Leistungen)',
            'Reisekosten' => 'Reisekosten',
            'Sonstiges' => 'Sonstiges',
        ];

        $tenant = auth()->user()?->tenant;
        $custom = $tenant ? (array) data_get($tenant->settings, 'custom_bank_account_types', []) : [];
        $customOptions = collect($custom)->mapWithKeys(fn ($val) => [$val => $val])->toArray();

        return array_merge($predefined, $customOptions);
    }

    /**
     * Neue Staatsangehoerigkeit in Tenant-Settings speichern.
     */
    public static function addCustomNationality(string $value): void
    {
        $tenant = auth()->user()?->tenant;
        if (! $tenant) return;

        $settings = $tenant->settings ?? [];
        $custom = (array) data_get($settings, 'custom_nationalities', []);

        if (! in_array($value, $custom)) {
            $custom[] = $value;
            sort($custom);
            data_set($settings, 'custom_nationalities', $custom);
            $tenant->update(['settings' => $settings]);
        }
    }
}
