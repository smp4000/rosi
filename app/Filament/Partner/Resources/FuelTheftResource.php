<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Components\SignaturePad;
use App\Filament\Partner\Resources\FuelTheftResource\Pages;
use App\Filament\Partner\Resources\FuelTheftResource\Widgets;
use App\Models\FuelTheft;
use App\Models\GasStation;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kraftstoffdiebstahl-Resource.
 * 5-Step Wizard: Vorfall → Fahrzeug → Umstaende → Belege → Abschluss.
 */
class FuelTheftResource extends Resource
{
    protected static ?string $model = FuelTheft::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Tankbetruege';

    protected static ?string $modelLabel = 'Tankbetrug';

    protected static ?string $pluralModelLabel = 'Tankbetruege';

    protected static string|\UnitEnum|null $navigationGroup = 'Kraftstoffe';

    protected static ?int $navigationSort = 10;

    // ── Kraftstoff-Optionen ────────────────────────

    public static function getProductOptions(): array
    {
        return [
            'super_e5' => 'Super E5',
            'super_e10' => 'Super E10',
            'diesel' => 'Diesel',
            'super_plus' => 'Super Plus',
            'ultimate_diesel' => 'Aral Ultimate Diesel',
            'ultimate_102' => 'Aral Ultimate 102',
        ];
    }

    // ── Status-Badges ──────────────────────────────

    public static function getStatusOptions(): array
    {
        return [
            'draft' => 'Entwurf',
            'submitted' => 'Gesendet',
            'in_progress' => 'In Bearbeitung',
            'resolved' => 'Erledigt',
            'rejected' => 'Abgelehnt',
        ];
    }

    public static function getStatusColors(): array
    {
        return [
            'draft' => 'gray',
            'submitted' => 'info',
            'in_progress' => 'warning',
            'resolved' => 'success',
            'rejected' => 'danger',
        ];
    }

    // ── Rechtsbelehrung ────────────────────────────

    public static function getLegalNoticeText(): string
    {
        return 'Ich weiss, dass die vorgenannten Angaben zur Vorlage bei der zustaendigen '
            . 'Polizeidienststelle / Weiterleitung an eine Rechtsanwaltskanzlei dienen, um '
            . 'zivilrechtliche Ansprueche gegen den Tankkunden durchzusetzen. Sie bilden die '
            . 'Grundlage fuer Erstattungsleistungen von Aral an den Tankstellenunternehmer. '
            . 'Mir ist bekannt, dass falsche Angaben den Tatbestand des Betruges erfuellen '
            . 'koennen. Ein Betrug kann gem. § 263 StGB mit einer Freiheitsstrafe bis zu fuenf '
            . 'Jahren oder Geldstrafe geahndet werden. Der vorstehende Sachverhalt ist '
            . 'vollstaendig und richtig wiedergegeben. Die Aufbewahrungsfrist fuer vorhandene '
            . 'Videobeweise liegt bei 12 Monaten.';
    }

    // ── Formular (fuer Edit-Seite) ─────────────────

    public static function form(Schema $schema): Schema
    {
        return $schema->schema(static::getFormSchema());
    }

    /**
     * Vollstaendiges Formular-Schema (wird im Wizard und in Edit verwendet).
     */
    public static function getFormSchema(): array
    {
        return [
            // Step 1: Vorfall
            Section::make('Vorfall')
                ->icon('heroicon-o-clock')
                ->schema(static::getStep1Schema()),

            // Step 2: Fahrzeug
            Section::make('Fahrzeug-Identifikation')
                ->icon('heroicon-o-truck')
                ->schema(static::getStep2Schema()),

            // Step 3: Umstaende
            Section::make('Umstaende & Zeuge')
                ->icon('heroicon-o-clipboard-document-list')
                ->schema(static::getStep3Schema()),

            // Step 4: Belege
            Section::make('Belege & Unterschrift')
                ->icon('heroicon-o-paper-clip')
                ->schema(static::getStep4Schema()),

            // Step 5: Rechtsbelehrung
            Section::make('Rechtsbelehrung')
                ->icon('heroicon-o-shield-check')
                ->schema(static::getStep5FormSchema()),
        ];
    }

    // ── Step 1: Vorfall ────────────────────────────

    public static function getStep1Schema(): array
    {
        return [
            Select::make('gas_station_id')
                ->label('Tankstelle')
                ->options(function () {
                    $tenantId = auth()->user()?->tenant_id;

                    return GasStation::where('tenant_id', $tenantId)->pluck('name', 'id');
                })
                ->required()
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(function (?string $state, Set $set) {
                    if (! $state) {
                        return;
                    }

                    $station = GasStation::find($state);
                    if (! $station) {
                        return;
                    }

                    // Produkt und Zapfpunkt zuruecksetzen (neue Optionen)
                    $set('product', null);
                    $set('pump_number', null);

                    // Kamera-Vorbelegung
                    if (! $station->has_camera) {
                        $set('camera_missing', true);
                    } else {
                        $set('camera_missing', false);
                    }
                })
                ->helperText('Waehlen Sie die Tankstelle aus, an der der Vorfall stattfand.'),

            DateTimePicker::make('incident_at')
                ->label('Zeitpunkt des Vorfalls')
                ->required()
                ->seconds(false)
                ->displayFormat('d.m.Y H:i')
                ->maxDate(now())
                ->default(now())
                ->helperText('Datum und Uhrzeit des Vorfalls (Format: TT.MM.JJJJ HH:MM)'),

            Grid::make(2)->schema([
                Select::make('product')
                    ->label('Produkt')
                    ->options(function (Get $get) {
                        $stationId = $get('gas_station_id');
                        if ($stationId) {
                            $station = GasStation::find($stationId);
                            if ($station && is_array($station->fuel_types)) {
                                return collect($station->fuel_types)
                                    ->mapWithKeys(fn ($type) => [$type => $type])
                                    ->toArray();
                            }
                        }

                        return static::getProductOptions();
                    })
                    ->required()
                    ->searchable(),

                Select::make('pump_number')
                    ->label('Zapfpunkt')
                    ->options(function (Get $get) {
                        $stationId = $get('gas_station_id');
                        if ($stationId) {
                            $station = GasStation::find($stationId);
                            $max = $station?->num_pumps ?? 12;
                        } else {
                            $max = 12;
                        }

                        return collect(range(1, $max))
                            ->mapWithKeys(fn ($n) => [$n => "Zapfpunkt {$n}"])
                            ->toArray();
                    })
                    ->required(),
            ]),

            Grid::make(2)->schema([
                TextInput::make('quantity')
                    ->label('Menge')
                    ->numeric()
                    ->required()
                    ->minValue(0.001)
                    ->step(0.001)
                    ->suffix('l / kg')
                    ->placeholder('z.B. 45.500'),

                TextInput::make('amount')
                    ->label('Betrag')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->suffix('EUR')
                    ->prefix('€')
                    ->placeholder('z.B. 85.50'),
            ]),
        ];
    }

    // ── Step 2: Fahrzeug ───────────────────────────

    public static function getStep2Schema(): array
    {
        return [
            Radio::make('license_plate_available')
                ->label('KFZ-Kennzeichen')
                ->options([
                    true => 'KFZ-Kennzeichen verfuegbar',
                    false => 'KFZ-Kennzeichen nicht verfuegbar',
                ])
                ->default(true)
                ->required()
                ->live()
                ->inline(),

            // Wenn Kennzeichen verfuegbar
            Group::make([
                TextInput::make('license_plate')
                    ->label('KFZ-Kennzeichen')
                    ->required(fn (Get $get) => (bool) $get('license_plate_available'))
                    ->placeholder('ABC-AB 1234')
                    ->maxLength(20)
                    ->helperText('Format: ABC-AB 1234')
                    ->extraInputAttributes(['style' => 'text-transform: uppercase']),

                Grid::make(2)->schema([
                    Checkbox::make('is_special_plate')
                        ->label('Spezielles / auslaendisches Kennzeichen'),

                    Checkbox::make('has_video_evidence')
                        ->label('Videobeweis liegt vor'),
                ]),
            ])
                ->visible(fn (Get $get) => (bool) $get('license_plate_available')),

            // Wenn Kennzeichen NICHT verfuegbar
            Group::make([
                Section::make('Grund fuer fehlendes Kennzeichen')
                    ->description('Bitte waehlen Sie mindestens einen Grund aus.')
                    ->schema([
                        Grid::make(2)->schema([
                            Checkbox::make('plate_not_readable')
                                ->label('Kennzeichen nicht eindeutig lesbar'),

                            Checkbox::make('plate_stolen')
                                ->label('Bekanntermassen gestohlenes Kennzeichen'),

                            Checkbox::make('camera_defective')
                                ->label('Videoanlage defekt'),

                            Checkbox::make('camera_missing')
                                ->label('Videoanlage nicht vorhanden'),
                        ]),

                        TextInput::make('no_plate_other_reason')
                            ->label('Sonstiges')
                            ->placeholder('Weiterer Grund...')
                            ->maxLength(255),
                    ]),
            ])
                ->visible(fn (Get $get) => ! (bool) $get('license_plate_available')),
        ];
    }

    // ── Step 3: Umstaende ──────────────────────────

    public static function getStep3Schema(): array
    {
        return [
            Grid::make(2)->schema([
                Checkbox::make('pump_confusion')
                    ->label('Saeulenverwechselung'),

                Checkbox::make('card_payment_error')
                    ->label('Fehler Kartenzahlung')
                    ->helperText('Transaktion wurde aus technischen Gruenden abgebrochen.'),
            ]),

            Radio::make('shop_purchase')
                ->label('Shopkauf')
                ->options([
                    'card' => 'Shopkauf mit Kartenzahlung',
                    'cash' => 'Shopkauf mit Barzahlung',
                    'none' => 'Kein Shopkauf',
                ])
                ->default('none')
                ->required(),

            Checkbox::make('transfer_inkasso')
                ->label('Transfer Inkasso'),

            Section::make('Kassierer / Zeuge')
                ->description('Daten des angemeldeten Mitarbeiters werden automatisch uebernommen.')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('witness_first_name')
                            ->label('Vorname')
                            ->default(fn () => auth()->user()?->first_name)
                            ->maxLength(255),

                        TextInput::make('witness_last_name')
                            ->label('Nachname')
                            ->default(fn () => auth()->user()?->last_name)
                            ->maxLength(255),
                    ]),

                    TextInput::make('witness_address')
                        ->label('Adresse')
                        ->default(fn () => auth()->user()?->employeeProfile?->street)
                        ->maxLength(255),

                    Grid::make(3)->schema([
                        TextInput::make('witness_zip')
                            ->label('PLZ')
                            ->default(fn () => auth()->user()?->employeeProfile?->zip)
                            ->maxLength(10),

                        TextInput::make('witness_city')
                            ->label('Stadt')
                            ->default(fn () => auth()->user()?->employeeProfile?->city)
                            ->maxLength(255),

                        TextInput::make('witness_country')
                            ->label('Land')
                            ->maxLength(5)
                            ->default('DE'),
                    ]),
                ]),

            Textarea::make('comment')
                ->label('Kommentar / Bemerkungen')
                ->required()
                ->rows(3)
                ->maxLength(2000)
                ->placeholder('Zusaetzliche Informationen zum Vorfall, z.B. Schuldanerkenntnis...'),
        ];
    }

    // ── Step 4: Belege & Unterschrift ───────────────

    public static function getStep4Schema(): array
    {
        return [
            FileUpload::make('receipt_path')
                ->label('Tankbeleg')
                ->required()
                ->disk('public')
                ->directory('fuel-thefts/receipts')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                ->maxSize(10240)
                ->imagePreviewHeight('200')
                ->helperText('Foto oder Scan des Tankbelegs (JPG, PNG oder PDF, max. 10 MB). Pflichtfeld!'),

            FileUpload::make('additional_documents')
                ->label('Weitere Belege')
                ->multiple()
                ->disk('public')
                ->directory('fuel-thefts/documents')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'video/mp4'])
                ->maxSize(20480)
                ->maxFiles(5)
                ->imagePreviewHeight('150')
                ->reorderable()
                ->helperText('Optionale weitere Belege: Kassenbon, Videoausschnitt, Fotos etc. (max. 5 Dateien, je max. 20 MB)'),

            SignaturePad::make('signature')
                ->label('Unterschrift des Mitarbeiters')
                ->required()
                ->confirmationText('Bitte unterschreiben Sie hier zur Bestaetigung Ihrer Angaben.')
                ->height(180),
        ];
    }

    // ── Step 5: Rechtsbelehrung (Wizard-Version) ───

    public static function getStep5Schema(): array
    {
        return [
            // Zusammenfassung
            Placeholder::make('summary')
                ->label('')
                ->content(function (Get $get) {
                    $product = static::getProductOptions()[$get('product')] ?? $get('product') ?? '-';
                    $shopPurchase = match ($get('shop_purchase')) {
                        'card' => 'Shopkauf mit Kartenzahlung',
                        'cash' => 'Shopkauf mit Barzahlung',
                        default => 'Kein Shopkauf',
                    };

                    $station = $get('gas_station_id')
                        ? GasStation::find($get('gas_station_id'))?->name ?? '-'
                        : '-';

                    $licensePlate = $get('license_plate_available')
                        ? ($get('license_plate') ?: '-')
                        : 'Nicht verfuegbar';

                    $incidentAt = $get('incident_at')
                        ? \Carbon\Carbon::parse($get('incident_at'))->format('d.m.Y H:i')
                        : '-';

                    $quantity = $get('quantity') ? number_format((float) $get('quantity'), 3, ',', '.') . ' l' : '-';
                    $amount = $get('amount') ? number_format((float) $get('amount'), 2, ',', '.') . ' EUR' : '-';

                    $extras = collect([
                        $get('pump_confusion') ? 'Saeulenverwechselung' : null,
                        $get('card_payment_error') ? 'Fehler Kartenzahlung' : null,
                        $get('transfer_inkasso') ? 'Transfer Inkasso' : null,
                        $get('has_video_evidence') ? 'Videobeweis vorhanden' : null,
                    ])->filter()->implode(', ') ?: 'Keine';

                    return new \Illuminate\Support\HtmlString("
                        <div style='background: #f1f5f9; border-radius: 12px; padding: 20px; font-size: 14px; line-height: 1.8;'>
                            <h3 style='margin: 0 0 12px 0; font-size: 16px; font-weight: 600; color: #1e293b;'>Zusammenfassung der Meldung</h3>
                            <table style='width: 100%; border-collapse: collapse;'>
                                <tr><td style='padding: 4px 12px 4px 0; font-weight: 500; color: #64748b; width: 180px;'>Tankstelle</td><td style='padding: 4px 0;'>{$station}</td></tr>
                                <tr><td style='padding: 4px 12px 4px 0; font-weight: 500; color: #64748b;'>Zeitpunkt</td><td style='padding: 4px 0;'>{$incidentAt}</td></tr>
                                <tr><td style='padding: 4px 12px 4px 0; font-weight: 500; color: #64748b;'>Produkt</td><td style='padding: 4px 0;'>{$product}</td></tr>
                                <tr><td style='padding: 4px 12px 4px 0; font-weight: 500; color: #64748b;'>Zapfpunkt</td><td style='padding: 4px 0;'>{$get('pump_number')}</td></tr>
                                <tr><td style='padding: 4px 12px 4px 0; font-weight: 500; color: #64748b;'>Menge</td><td style='padding: 4px 0;'>{$quantity}</td></tr>
                                <tr><td style='padding: 4px 12px 4px 0; font-weight: 500; color: #64748b;'>Betrag</td><td style='padding: 4px 0; font-weight: 600; color: #dc2626;'>{$amount}</td></tr>
                                <tr><td style='padding: 4px 12px 4px 0; font-weight: 500; color: #64748b;'>KFZ-Kennzeichen</td><td style='padding: 4px 0; font-weight: 600;'>{$licensePlate}</td></tr>
                                <tr><td style='padding: 4px 12px 4px 0; font-weight: 500; color: #64748b;'>Shopkauf</td><td style='padding: 4px 0;'>{$shopPurchase}</td></tr>
                                <tr><td style='padding: 4px 12px 4px 0; font-weight: 500; color: #64748b;'>Besonderheiten</td><td style='padding: 4px 0;'>{$extras}</td></tr>
                            </table>
                        </div>
                    ");
                }),

            // Rechtsbelehrung
            Placeholder::make('legal_notice_text')
                ->label('Rechtsbelehrung')
                ->content(new \Illuminate\Support\HtmlString(
                    '<div style="background: #fffbeb; border: 1px solid #f59e0b; border-radius: 8px; padding: 16px; font-size: 13px; line-height: 1.7; color: #92400e;">'
                    . '<strong style="display: block; margin-bottom: 8px; font-size: 14px;">Bitte lesen Sie die folgende Rechtsbelehrung sorgfaeltig:</strong>'
                    . static::getLegalNoticeText()
                    . '</div>'
                )),

            Checkbox::make('legal_notice_accepted')
                ->label('Ich habe die Rechtsbelehrung gelesen und akzeptiere diese.')
                ->required()
                ->accepted()
                ->validationMessages([
                    'accepted' => 'Sie muessen die Rechtsbelehrung akzeptieren um fortzufahren.',
                ]),
        ];
    }

    /**
     * Step 5 fuer Edit-Form (ohne live Zusammenfassung).
     */
    public static function getStep5FormSchema(): array
    {
        return [
            Placeholder::make('legal_notice_text')
                ->label('Rechtsbelehrung')
                ->content(new \Illuminate\Support\HtmlString(
                    '<div style="background: #fffbeb; border: 1px solid #f59e0b; border-radius: 8px; padding: 16px; font-size: 13px; line-height: 1.7; color: #92400e;">'
                    . static::getLegalNoticeText()
                    . '</div>'
                )),

            Checkbox::make('legal_notice_accepted')
                ->label('Ich habe die Rechtsbelehrung gelesen und akzeptiere diese.')
                ->required()
                ->accepted(),
        ];
    }

    // ── Tabelle ────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('incident_at')
                    ->label('Zeitpunkt')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('gasStation.name')
                    ->label('Tankstelle')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('product')
                    ->label('Produkt')
                    ->formatStateUsing(fn (string $state) => static::getProductOptions()[$state] ?? $state)
                    ->toggleable(),

                TextColumn::make('pump_number')
                    ->label('Zapfpunkt')
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('amount')
                    ->label('Betrag')
                    ->money('EUR')
                    ->sortable()
                    ->color('danger')
                    ->weight('bold'),

                TextColumn::make('quantity')
                    ->label('Menge')
                    ->suffix(' l')
                    ->numeric(3)
                    ->toggleable(),

                TextColumn::make('license_plate')
                    ->label('Kennzeichen')
                    ->searchable()
                    ->placeholder('Nicht verfuegbar')
                    ->weight('bold'),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state) => static::getStatusOptions()[$state] ?? $state)
                    ->colors(static::getStatusColors())
                    ->sortable(),

                BadgeColumn::make('payment_status')
                    ->label('Zahlung')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'open' => 'Offen',
                        'partial' => 'Teilzahlung',
                        'paid' => 'Bezahlt',
                        'written_off' => 'Abgeschrieben',
                        default => $state,
                    })
                    ->colors([
                        'danger' => 'open',
                        'warning' => 'partial',
                        'success' => 'paid',
                        'gray' => 'written_off',
                    ])
                    ->sortable(),

                TextColumn::make('reporter.name')
                    ->label('Gemeldet von')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                TableGroup::make('gasStation.name')
                    ->label('Tankstelle')
                    ->collapsible(),
            ])
            ->defaultSort('incident_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(static::getStatusOptions()),

                SelectFilter::make('gas_station_id')
                    ->label('Tankstelle')
                    ->relationship('gasStation', 'name'),

                SelectFilter::make('product')
                    ->label('Produkt')
                    ->options(static::getProductOptions()),

                SelectFilter::make('payment_status')
                    ->label('Zahlung')
                    ->options([
                        'open' => 'Offen',
                        'partial' => 'Teilzahlung',
                        'paid' => 'Bezahlt',
                        'written_off' => 'Abgeschrieben',
                    ]),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    // ── Seiten ─────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFuelThefts::route('/'),
            'create' => Pages\CreateFuelTheft::route('/create'),
            'edit' => Pages\EditFuelTheft::route('/{record}/edit'),
            'view' => Pages\ViewFuelTheft::route('/{record}'),
        ];
    }

    // ── Widgets ────────────────────────────────────

    public static function getWidgets(): array
    {
        return [
            Widgets\FuelTheftStatsOverview::class,
        ];
    }

    // ── Eloquent Query ─────────────────────────────

    public static function getEloquentQuery(): Builder
    {
        $tenantId = auth()->user()?->tenant_id;

        return parent::getEloquentQuery()
            ->where('tenant_id', $tenantId);
    }
}
