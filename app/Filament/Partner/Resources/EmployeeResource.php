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
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament Resource fuer die Mitarbeiter-Verwaltung im Partner-Panel.
 * 10-Tab DATV-Personalbogen — alle Felder editierbar.
 */
class EmployeeResource extends Resource
{
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

    // --- Autorisierung ---

    protected static function ensureTeamContext(): void
    {
        $user = auth()->user();
        if ($user?->tenant_id) {
            app(\Spatie\Permission\PermissionRegistrar::class)
                ->setPermissionsTeamId($user->tenant_id);
        }
    }

    public static function canAccess(): bool
    {
        static::ensureTeamContext();

        return auth()->user()->can('partner.employees.list');
    }

    public static function canCreate(): bool
    {
        static::ensureTeamContext();

        return auth()->user()->can('partner.employees.create');
    }

    public static function canEdit(Model $record): bool
    {
        static::ensureTeamContext();

        return auth()->user()->can('partner.employees.edit');
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canView(Model $record): bool
    {
        static::ensureTeamContext();

        return auth()->user()->can('partner.employees.view');
    }

    // --- Query: Nur Mitarbeiter des eigenen Mandanten ---

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('type', 'employee')
            ->where('tenant_id', session('tenant_id'))
            ->with(['employeeProfile', 'gasStations', 'documents']);
    }

    /**
     * Deutsche IBAN aus BLZ + Kontonummer berechnen.
     */
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
                            Select::make('employeeProfile.gender')
                                ->label(__('partner.employee.fields.gender'))
                                ->options(__('partner.employee.genders')),
                            TextInput::make('employeeProfile.date_of_birth')
                                ->label(__('partner.employee.fields.date_of_birth'))
                                ->placeholder('TT.MM.JJJJ'),
                            TextInput::make('employeeProfile.place_of_birth')
                                ->label(__('partner.employee.fields.place_of_birth'))
                                ->maxLength(255),
                            TextInput::make('employeeProfile.nationality')
                                ->label(__('partner.employee.fields.nationality'))
                                ->maxLength(100),
                            TextInput::make('employeeProfile.second_nationality')
                                ->label(__('partner.employee.fields.second_nationality'))
                                ->maxLength(100),
                            Select::make('employeeProfile.marital_status')
                                ->label(__('partner.employee.fields.marital_status'))
                                ->options(__('partner.employee.marital_statuses')),
                            Select::make('gasStations')
                                ->label('Tankstelle(n)')
                                ->relationship('gasStations', 'name', fn (Builder $query) => $query->where('tenant_id', session('tenant_id')))
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->placeholder('Tankstelle(n) zuordnen')
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
                            TextInput::make('employeeProfile.health_insurance_name')
                                ->label(__('partner.employee.fields.health_insurance_name'))
                                ->maxLength(255)
                                ->placeholder('z.B. AOK, TK, Barmer'),
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
                            Section::make('Bankdaten')
                                ->description('Bankdaten werden verschluesselt gespeichert (AES-256).')
                                ->icon('heroicon-o-lock-closed')
                                ->schema([
                                    TextInput::make('employeeProfile.account_holder')
                                        ->label(__('partner.employee.fields.account_holder'))
                                        ->maxLength(255)
                                        ->columnSpanFull(),

                                    TextInput::make('employeeProfile.iban')
                                        ->label(__('partner.employee.fields.iban'))
                                        ->maxLength(34)
                                        ->placeholder('DE89 3704 0044 0532 0130 00'),
                                    TextInput::make('employeeProfile.bic')
                                        ->label(__('partner.employee.fields.bic'))
                                        ->maxLength(11)
                                        ->placeholder('z.B. COBADEFFXXX'),
                                    TextInput::make('employeeProfile.bank_name')
                                        ->label(__('partner.employee.fields.bank_name'))
                                        ->maxLength(255)
                                        ->placeholder('z.B. Commerzbank'),
                                ])
                                ->columns(2),

                            Section::make('IBAN-Rechner')
                                ->description('Optional: IBAN automatisch aus Kontonummer und BLZ berechnen.')
                                ->icon('heroicon-o-calculator')
                                ->collapsed()
                                ->schema([
                                    TextInput::make('_blz')
                                        ->label('Bankleitzahl (BLZ)')
                                        ->maxLength(8)
                                        ->placeholder('8-stellige BLZ')
                                        ->dehydrated(false)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                            $kontonummer = $get('_kontonummer');
                                            if ($state && $kontonummer) {
                                                $iban = static::calculateGermanIban($state, $kontonummer);
                                                if ($iban) {
                                                    $set('employeeProfile.iban', $iban);
                                                }
                                            }
                                        }),
                                    TextInput::make('_kontonummer')
                                        ->label('Kontonummer')
                                        ->maxLength(10)
                                        ->placeholder('Max. 10 Stellen')
                                        ->dehydrated(false)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                                            $blz = $get('_blz');
                                            if ($blz && $state) {
                                                $iban = static::calculateGermanIban($blz, $state);
                                                if ($iban) {
                                                    $set('employeeProfile.iban', $iban);
                                                }
                                            }
                                        }),
                                ])
                                ->columns(2),
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
                            CheckboxList::make('employeeProfile.drivers_license')
                                ->label(__('partner.employee.fields.drivers_license'))
                                ->options([
                                    'B' => 'B', 'BE' => 'BE', 'C' => 'C', 'CE' => 'CE',
                                    'C1' => 'C1', 'C1E' => 'C1E', 'AM' => 'AM',
                                    'A1' => 'A1', 'A2' => 'A2', 'A' => 'A',
                                ])
                                ->columns(5)
                                ->columnSpanFull(),
                            DatePicker::make('employeeProfile.drivers_license_expiry')
                                ->label(__('partner.employee.fields.drivers_license_expiry'))
                                ->displayFormat('d.m.Y'),

                            Section::make('Pflichtschulungen')
                                ->description('Gesetzlich vorgeschriebene Schulungen und deren Termine.')
                                ->icon('heroicon-o-shield-check')
                                ->schema([
                                    DatePicker::make('employeeProfile.safety_training_date')
                                        ->label(__('partner.employee.fields.safety_training_date'))
                                        ->displayFormat('d.m.Y'),
                                    DatePicker::make('employeeProfile.first_aid_training_date')
                                        ->label(__('partner.employee.fields.first_aid_training_date'))
                                        ->displayFormat('d.m.Y'),
                                    DatePicker::make('employeeProfile.hazmat_training_date')
                                        ->label(__('partner.employee.fields.hazmat_training_date'))
                                        ->displayFormat('d.m.Y'),
                                ])
                                ->columns(3)
                                ->columnSpanFull(),

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
                    // Tab 8: Arbeitsmedizin
                    // =============================================
                    Tab::make(__('partner.employee.tabs.arbeitsmedizin'))
                        ->icon('heroicon-o-clipboard-document-check')
                        ->schema([
                            DatePicker::make('employeeProfile.medical_exam_date')
                                ->label(__('partner.employee.fields.medical_exam_date'))
                                ->displayFormat('d.m.Y'),
                            DatePicker::make('employeeProfile.medical_exam_next')
                                ->label(__('partner.employee.fields.medical_exam_next'))
                                ->displayFormat('d.m.Y'),
                            Textarea::make('employeeProfile.medical_restrictions')
                                ->label(__('partner.employee.fields.medical_restrictions'))
                                ->rows(3)
                                ->columnSpanFull()
                                ->placeholder('z.B. Keine schweren Lasten ueber 15kg'),
                            Toggle::make('employeeProfile.health_certificate')
                                ->label(__('partner.employee.fields.health_certificate')),
                            DatePicker::make('employeeProfile.health_certificate_date')
                                ->label(__('partner.employee.fields.health_certificate_date'))
                                ->displayFormat('d.m.Y'),

                            Section::make('Aufenthaltstitel')
                                ->icon('heroicon-o-identification')
                                ->schema([
                                    TextInput::make('employeeProfile.residence_permit_type')
                                        ->label(__('partner.employee.fields.residence_permit_type'))
                                        ->maxLength(100)
                                        ->placeholder('z.B. Aufenthaltserlaubnis, Niederlassungserlaubnis'),
                                    DatePicker::make('employeeProfile.residence_permit_expires')
                                        ->label(__('partner.employee.fields.residence_permit_expires'))
                                        ->displayFormat('d.m.Y'),
                                ])
                                ->columns(2)
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    // =============================================
                    // Tab 9: Dokumente
                    // =============================================
                    Tab::make(__('partner.employee.tabs.dokumente'))
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            // --- Aktion: Dokument aus Vorlage erstellen (mit Vorschau + KI) ---
                            SchemaActions::make([
                                Actions\Action::make('create_document_from_template')
                                    ->label('Neues Dokument erstellen')
                                    ->icon('heroicon-o-document-plus')
                                    ->color('primary')
                                    ->form([
                                        Select::make('template_id')
                                            ->label('Vorlage')
                                            ->options(fn () => DocumentTemplate::query()
                                                ->where(function ($q) {
                                                    $q->whereNull('tenant_id')
                                                        ->orWhere('tenant_id', session('tenant_id'));
                                                })
                                                ->where('is_active', true)
                                                ->orderBy('sort_order')
                                                ->pluck('name', 'id'))
                                            ->required()
                                            ->searchable()
                                            ->live()
                                            ->afterStateUpdated(function ($state, Set $set, $record) {
                                                if (! $state) {
                                                    $set('content', '');
                                                    return;
                                                }
                                                $template = DocumentTemplate::find($state);
                                                if ($template && $record) {
                                                    $service = app(DocumentService::class);
                                                    $variables = $service->buildVariableMap($record);
                                                    $content = $service->replacePlaceholders($template->content, $variables);
                                                    $set('content', $content);
                                                }
                                            }),
                                        Select::make('gas_station_id')
                                            ->label('Tankstelle')
                                            ->options(fn () => \App\Models\GasStation::query()
                                                ->where('tenant_id', session('tenant_id'))
                                                ->pluck('name', 'id'))
                                            ->searchable()
                                            ->placeholder('Optional — Tankstelle zuordnen'),
                                        RichEditor::make('content')
                                            ->label('Dokumentinhalt')
                                            ->helperText('Der Inhalt wurde aus der Vorlage generiert. Sie koennen ihn hier bearbeiten.')
                                            ->toolbarButtons([
                                                'bold', 'italic', 'underline',
                                                'h2', 'h3',
                                                'bulletList', 'orderedList',
                                                'blockquote', 'link',
                                                'undo', 'redo',
                                            ])
                                            ->columnSpanFull(),
                                        Toggle::make('generate_pdf')
                                            ->label('PDF sofort generieren')
                                            ->default(true),
                                        Toggle::make('send_email')
                                            ->label('Direkt per E-Mail versenden (mit Signatur-Link)')
                                            ->default(false),
                                    ])
                                    ->action(function (array $data, $record) {
                                        $template = DocumentTemplate::findOrFail($data['template_id']);
                                        $station = ! empty($data['gas_station_id'])
                                            ? \App\Models\GasStation::find($data['gas_station_id'])
                                            : null;

                                        // Dokument mit bearbeitetem Inhalt erstellen
                                        $document = Document::create([
                                            'tenant_id' => auth()->user()->tenant_id,
                                            'user_id' => $record->id,
                                            'gas_station_id' => $station?->id,
                                            'template_id' => $template->id,
                                            'type' => $template->type,
                                            'category' => $template->category ?? 'hr',
                                            'title' => $template->name,
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
                                    ->modalDescription('Waehlen Sie eine Vorlage. Der Inhalt wird mit den Mitarbeiterdaten ausgefuellt und kann bearbeitet werden.')
                                    ->modalSubmitActionLabel('Dokument erstellen')
                                    ->modalWidth('5xl'),

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
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
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
}
