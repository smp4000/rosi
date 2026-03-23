<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\CorporateCustomerResource\Pages;
use App\Filament\Partner\Resources\CorporateCustomerResource\RelationManagers;
use App\Models\CorporateCustomer;
use App\Models\GasStation;
use Filament\Actions;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

/**
 * Partner-Resource fuer Firmenkunden-Verwaltung.
 * Tab-basiertes Design mit 4 Tabs: Stammdaten, Bankdaten, Konditionen, Tankkarten.
 */
class CorporateCustomerResource extends Resource
{
    protected static ?string $model = CorporateCustomer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static string|\UnitEnum|null $navigationGroup = 'Tankstellen';

    protected static ?string $modelLabel = 'Firmenkunde';

    protected static ?string $pluralModelLabel = 'Firmenkunden';

    protected static ?int $navigationSort = 14;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        $tenantId = auth()->user()?->tenant_id;
        if (! $tenantId) {
            return null;
        }

        $count = CorporateCustomer::whereHas('gasStation', fn ($q) => $q->where('tenant_id', $tenantId))->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getEloquentQuery(): Builder
    {
        $tenantId = auth()->user()?->tenant_id;

        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->whereHas('gasStation', fn (Builder $q) => $q->where('tenant_id', $tenantId));
    }

    public static function form(Schema $schema): Schema
    {
        $tenantId = auth()->user()?->tenant_id;

        return $schema->components([
            // Kunden-Zusammenfassung (nur bei Edit)
            Placeholder::make('customer_summary')
                ->label('')
                ->visibleOn('edit')
                ->content(function (?CorporateCustomer $record): HtmlString {
                    if (! $record) {
                        return new HtmlString('');
                    }
                    $invoiceCount = $record->invoices()->count();
                    $totalAmount = $record->invoices()->sum('amount');
                    $lastInvoice = $record->invoices()->orderByDesc('invoice_date')->first();
                    $initial = strtoupper(substr($record->name ?? 'K', 0, 1));
                    $lastDate = ($lastInvoice?->invoice_date instanceof \Carbon\Carbon) ? $lastInvoice->invoice_date->format('d.m.Y') : ($lastInvoice?->invoice_date ?? '-');
                    $emailDisplay = $record->email ? " &nbsp; ✉ {$record->email}" : '';
                    $emailBtn = ($record->email && $record->email !== 'smp4000@me.com')
                        ? "<a href='mailto:{$record->email}' style='display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:1px solid #e5e7eb;border-radius:8px;text-decoration:none;color:#374151;font-size:0.85rem;'>✉ E-Mail</a>"
                        : '';

                    $html = "<div style='background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;'>";
                    $html .= "<div style='display:flex;align-items:center;justify-content:space-between;margin-bottom:" . ($invoiceCount > 0 ? '20px' : '0') . ";'>";
                    $html .= "<div style='display:flex;align-items:center;gap:16px;'>";
                    $html .= "<div style='width:56px;height:56px;border-radius:50%;background:#dc2626;color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:bold;'>{$initial}</div>";
                    $html .= "<div><div style='font-size:1.2rem;font-weight:700;color:#1a202c;'>{$record->name}</div>";
                    $html .= "<div style='font-size:0.85rem;color:#6b7280;'># {$record->customer_number}{$emailDisplay}</div></div></div>";
                    $html .= $emailBtn;
                    $html .= "</div>";

                    if ($invoiceCount > 0) {
                        $totalFormatted = number_format($totalAmount, 2, ',', '.');
                        $html .= "<div style='display:grid;grid-template-columns:repeat(3,1fr);gap:16px;'>";
                        $html .= "<div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;'><div style='font-size:0.75rem;color:#dc2626;font-weight:600;text-transform:uppercase;'>Rechnungen</div><div style='font-size:1.5rem;font-weight:700;'>{$invoiceCount}</div></div>";
                        $html .= "<div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;'><div style='font-size:0.75rem;color:#dc2626;font-weight:600;text-transform:uppercase;'>Gesamtbetrag</div><div style='font-size:1.5rem;font-weight:700;'>{$totalFormatted} &euro;</div></div>";
                        $html .= "<div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;'><div style='font-size:0.75rem;color:#dc2626;font-weight:600;text-transform:uppercase;'>Letzte Rechnung</div><div style='font-size:1.5rem;font-weight:700;'>{$lastDate}</div></div>";
                        $html .= "</div>";
                    }

                    $html .= "</div>";

                    return new HtmlString($html);
                })
                ->columnSpanFull(),

            Tabs::make('Firmenkunde')
                ->tabs([
                    // ========== TAB 1: STAMMDATEN ==========
                    Tab::make('Stammdaten')
                        ->icon('heroicon-o-user')
                        ->schema([
                            // Platzhalter-E-Mail Warnung
                            Placeholder::make('placeholder_email_warning')
                                ->label('')
                                ->visibleOn('edit')
                                ->visible(fn (Get $get): bool => ($get('email') === 'smp4000@me.com') && ($get('send_via_email') ?? true))
                                ->content(new HtmlString(
                                    '<div style="padding:12px 16px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;display:flex;align-items:flex-start;gap:12px;">'
                                    . '<span style="font-size:1.3rem;">⚠️</span>'
                                    . '<div>'
                                    . '<div style="font-weight:600;color:#b45309;">Platzhalter-E-Mail erkannt</div>'
                                    . '<div style="font-size:0.85rem;color:#92400e;">Bitte eine echte E-Mail-Adresse eintragen, sonst gehen alle Rechnungen an smp4000@me.com.</div>'
                                    . '</div></div>'
                                ))
                                ->columnSpanFull(),

                            Section::make('Zuordnung')
                                ->schema([
                                    Select::make('gas_station_id')
                                        ->label('Tankstelle')
                                        ->options(function () use ($tenantId) {
                                            return GasStation::where('tenant_id', $tenantId)->pluck('name', 'id');
                                        })
                                        ->required()
                                        ->searchable()
                                        ->preload(),

                                    TextInput::make('customer_number')
                                        ->label('Firmennummer')
                                        ->required()
                                        ->numeric()
                                        ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule, Get $get) {
                                            return $rule->where('gas_station_id', $get('gas_station_id'));
                                        }),
                                ])
                                ->columns(2),

                            Section::make('Kontaktdaten')
                                ->schema([
                                    Select::make('salutation')
                                        ->label('Anrede')
                                        ->options([
                                            'Herr' => 'Herr',
                                            'Frau' => 'Frau',
                                            'Firma' => 'Firma',
                                            'Herr Dr.' => 'Herr Dr.',
                                            'Frau Dr.' => 'Frau Dr.',
                                            'Herr Dr. med.' => 'Herr Dr. med.',
                                            'Herr Dr. dent.' => 'Herr Dr. dent.',
                                            'Herrn' => 'Herrn',
                                            'An die' => 'An die',
                                        ])
                                        ->searchable(),

                                    TextInput::make('name')
                                        ->label('Name / Firma')
                                        ->required()
                                        ->maxLength(255),

                                    TextInput::make('name_2')
                                        ->label('Name Zeile 2')
                                        ->maxLength(255),

                                    TextInput::make('department')
                                        ->label('Abteilung / Zusatz')
                                        ->maxLength(255),

                                    TextInput::make('street')
                                        ->label('Strasse')
                                        ->maxLength(255),

                                    TextInput::make('zip')
                                        ->label('PLZ')
                                        ->maxLength(10),

                                    TextInput::make('city')
                                        ->label('Stadt')
                                        ->maxLength(255),

                                    TextInput::make('phone')
                                        ->label('Telefon')
                                        ->tel()
                                        ->maxLength(100),

                                    TextInput::make('email')
                                        ->label('E-Mail')
                                        ->email()
                                        ->maxLength(255)
                                        ->default('smp4000@me.com')
                                        ->helperText('Fuer zukuenftigen Rechnungsversand'),

                                    TextInput::make('notes')
                                        ->label('Notizen')
                                        ->maxLength(500)
                                        ->columnSpanFull(),
                                ])
                                ->columns(2),
                        ]),

                    // ========== TAB 2: BANKDATEN ==========
                    Tab::make('Bankdaten')
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Section::make('Zahlungsart')
                                ->schema([
                                    Select::make('payment_method')
                                        ->label('Zahlart')
                                        ->options([
                                            'Bar' => 'Bar',
                                            'SEPA - Basislastschrift' => 'SEPA - Basislastschrift',
                                            'SEPA - Firmenlastschrift' => 'SEPA - Firmenlastschrift',
                                        ])
                                        ->default('Bar')
                                        ->required()
                                        ->live(),
                                ])
                                ->columns(1),

                            Section::make('SEPA-Bankverbindung')
                                ->schema([
                                    TextInput::make('iban')
                                        ->label('IBAN')
                                        ->maxLength(34)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Set $set, ?string $state) {
                                            if ($state && strlen(preg_replace('/\s+/', '', $state)) >= 15) {
                                                $iban = preg_replace('/\s+/', '', $state);
                                                $bankData = EmployeeResource::lookupBankDataPublic($iban);
                                                if ($bankData['bic']) {
                                                    $set('bic', $bankData['bic']);
                                                }
                                                if ($bankData['bankName']) {
                                                    $set('bank_name', $bankData['bankName']);
                                                }
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
                                                    $iban = EmployeeResource::calculateGermanIbanPublic(
                                                        $data['blz'],
                                                        $data['kontonummer']
                                                    );
                                                    if ($iban) {
                                                        $set('iban', $iban);
                                                        $bankData = EmployeeResource::lookupBankDataPublic($iban);
                                                        if ($bankData['bic']) {
                                                            $set('bic', $bankData['bic']);
                                                        }
                                                        if ($bankData['bankName']) {
                                                            $set('bank_name', $bankData['bankName']);
                                                        }
                                                    }
                                                })
                                        ),

                                    TextInput::make('bic')
                                        ->label('BIC')
                                        ->maxLength(11),

                                    TextInput::make('bank_name')
                                        ->label('Bank')
                                        ->maxLength(255),

                                    TextInput::make('mandate_reference')
                                        ->label('SEPA-Mandatsreferenz')
                                        ->maxLength(100),

                                    TextInput::make('contract_number')
                                        ->label('Vertragsnummer')
                                        ->maxLength(100),
                                ])
                                ->columns(2)
                                ->visible(fn (Get $get): bool => str_starts_with($get('payment_method') ?? '', 'SEPA')),
                        ]),

                    // ========== TAB 3: KONDITIONEN ==========
                    Tab::make('Konditionen')
                        ->icon('heroicon-o-calculator')
                        ->schema([
                            Section::make('Finanzen')
                                ->schema([
                                    TextInput::make('discount_percent')
                                        ->label('Skonto')
                                        ->numeric()
                                        ->step(0.01)
                                        ->suffix('%')
                                        ->default(0.00),

                                    TextInput::make('credit_limit')
                                        ->label('Kreditlimit')
                                        ->numeric()
                                        ->step(0.01)
                                        ->prefix('EUR')
                                        ->default(0.00),

                                    TextInput::make('prepayment')
                                        ->label('Vorauszahlung')
                                        ->numeric()
                                        ->step(0.01)
                                        ->prefix('EUR')
                                        ->default(0.00),
                                ])
                                ->columns(3),

                            Section::make('Druckoptionen')
                                ->schema([
                                    Toggle::make('print_delivery_note')
                                        ->label('Lieferschein drucken')
                                        ->default(false),

                                    Toggle::make('print_prepayment')
                                        ->label('Vorauszahlung drucken')
                                        ->default(false),
                                ])
                                ->columns(2),

                            Section::make('Rechnungsversand')
                                ->description('Wie erhaelt der Kunde seine Rechnungen?')
                                ->schema([
                                    Toggle::make('send_via_email')
                                        ->label('Per E-Mail versenden')
                                        ->helperText('Rechnung als PDF per E-Mail')
                                        ->default(true),

                                    Toggle::make('send_via_print')
                                        ->label('Drucken')
                                        ->helperText('Im Sammel-PDF fuer Druck')
                                        ->default(false),

                                    Toggle::make('is_active')
                                        ->label('Kunde aktiv')
                                        ->helperText('Inaktive Kunden werden uebersprungen')
                                        ->default(true),
                                ])
                                ->columns(3),
                        ]),

                    // ========== TAB 4: TANKKARTEN ==========
                    Tab::make('Tankkarten')
                        ->icon('heroicon-o-credit-card')
                        ->schema([
                            Placeholder::make('fuel_cards_info')
                                ->label('')
                                ->content(function (?CorporateCustomer $record): HtmlString {
                                    if (! $record) {
                                        return new HtmlString('<span style="color:#6b7280;">Speichern Sie den Firmenkunden zuerst, um Tankkarten hinzuzufuegen.</span>');
                                    }
                                    $count = $record->fuelCards()->count();
                                    $icon = $count > 0 ? '💳' : '📭';

                                    return new HtmlString("<span style='font-size:0.9rem;'>{$icon} {$count} Tankkarte(n) zugeordnet. Verwalten Sie die Karten im Tab unterhalb.</span>");
                                }),
                        ])
                        ->visibleOn('edit'),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $tenantId = auth()->user()?->tenant_id;

        return $table
            ->columns([
            TextColumn::make('gasStation.name')
                    ->label('Tankstelle')
                    ->description(fn ($record) => $record->gasStation?->station_number_shop ?? $record->gasStation?->station_number ?? '')
                    ->sortable()
                    ->toggleable(),  

                TextColumn::make('customer_number')
                    ->label('Firmen-Nr.')
                    ->sortable()
                    ->searchable()
                    ->fontFamily('mono'),

                TextColumn::make('name')
                    ->label('Name / Firma')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (CorporateCustomer $record): ?string => $record->department),

                TextColumn::make('city')
                    ->label('Stadt')
                    ->sortable()
                    ->toggleable(),

                BadgeColumn::make('payment_method')
                    ->label('Zahlart')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'Bar' => 'Bar',
                        'SEPA - Basislastschrift' => 'SEPA Basis',
                        'SEPA - Firmenlastschrift' => 'SEPA Firma',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Bar' => 'gray',
                        'SEPA - Basislastschrift' => 'info',
                        'SEPA - Firmenlastschrift' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('fuel_cards_count')
                    ->label('Tankkarten')
                    ->counts('fuelCards')
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                TextColumn::make('email')
                    ->label('E-Mail')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('credit_limit')
                    ->label('Limit')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('imported_at')
                    ->label('Importiert')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('customer_number', 'asc')
            ->filters([
                SelectFilter::make('gas_station_id')
                    ->label('Tankstelle')
                    ->options(function () use ($tenantId) {
                        return GasStation::where('tenant_id', $tenantId)->pluck('name', 'id');
                    }),

                SelectFilter::make('payment_method')
                    ->label('Zahlart')
                    ->options([
                        'Bar' => 'Bar',
                        'SEPA - Basislastschrift' => 'SEPA - Basislastschrift',
                        'SEPA - Firmenlastschrift' => 'SEPA - Firmenlastschrift',
                    ]),

                TrashedFilter::make(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\FuelCardsRelationManager::class,
            RelationManagers\InvoicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCorporateCustomers::route('/'),
            'create' => Pages\CreateCorporateCustomer::route('/create'),
            'edit' => Pages\EditCorporateCustomer::route('/{record}/edit'),
        ];
    }
}
