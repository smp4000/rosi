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

    protected static ?int $navigationSort = 14;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('partner.corporate_customer.nav_group');
    }

    public static function getModelLabel(): string
    {
        return __('partner.corporate_customer.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('partner.corporate_customer.plural');
    }

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
                    $safeName = e($record->name ?? 'K');
                    $safeEmail = e($record->email ?? '');
                    $initial = strtoupper(substr($record->name ?? 'K', 0, 1));
                    $lastDate = ($lastInvoice?->invoice_date instanceof \Carbon\Carbon) ? $lastInvoice->invoice_date->format('d.m.Y') : ($lastInvoice?->invoice_date ?? '-');
                    $emailDisplay = $safeEmail ? " &nbsp; ✉ {$safeEmail}" : '';
                    $emailBtn = ($record->email && $record->email !== CorporateCustomer::PLACEHOLDER_EMAIL)
                        ? "<a href='mailto:{$safeEmail}' style='display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:1px solid #e5e7eb;border-radius:8px;text-decoration:none;color:#374151;font-size:0.85rem;'>✉ " . __('partner.corporate_customer.fields.email') . "</a>"
                        : '';

                    $statsInvoices = __('partner.corporate_customer.stats.invoices');
                    $statsTotalAmount = __('partner.corporate_customer.stats.total_amount');
                    $statsLastInvoice = __('partner.corporate_customer.stats.last_invoice');

                    $html = "<div style='background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;'>";
                    $html .= "<div style='display:flex;align-items:center;justify-content:space-between;margin-bottom:" . ($invoiceCount > 0 ? '20px' : '0') . ";'>";
                    $html .= "<div style='display:flex;align-items:center;gap:16px;'>";
                    $html .= "<div style='width:56px;height:56px;border-radius:50%;background:#dc2626;color:#fff;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:bold;'>{$initial}</div>";
                    $html .= "<div><div style='font-size:1.2rem;font-weight:700;color:#1a202c;'>{$safeName}</div>";
                    $html .= "<div style='font-size:0.85rem;color:#6b7280;'># {$record->customer_number}{$emailDisplay}</div></div></div>";
                    $html .= $emailBtn;
                    $html .= "</div>";

                    if ($invoiceCount > 0) {
                        $totalFormatted = number_format($totalAmount, 2, ',', '.');
                        $html .= "<div style='display:grid;grid-template-columns:repeat(3,1fr);gap:16px;'>";
                        $html .= "<div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;'><div style='font-size:0.75rem;color:#dc2626;font-weight:600;text-transform:uppercase;'>{$statsInvoices}</div><div style='font-size:1.5rem;font-weight:700;'>{$invoiceCount}</div></div>";
                        $html .= "<div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;'><div style='font-size:0.75rem;color:#dc2626;font-weight:600;text-transform:uppercase;'>{$statsTotalAmount}</div><div style='font-size:1.5rem;font-weight:700;'>{$totalFormatted} &euro;</div></div>";
                        $html .= "<div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;'><div style='font-size:0.75rem;color:#dc2626;font-weight:600;text-transform:uppercase;'>{$statsLastInvoice}</div><div style='font-size:1.5rem;font-weight:700;'>{$lastDate}</div></div>";
                        $html .= "</div>";
                    }

                    $html .= "</div>";

                    return new HtmlString($html);
                })
                ->columnSpanFull(),

            Tabs::make(__('partner.corporate_customer.label'))
                ->tabs([
                    // ========== TAB 1: STAMMDATEN ==========
                    Tab::make(__('partner.corporate_customer.tabs.stammdaten'))
                        ->icon('heroicon-o-user')
                        ->schema([
                            // Platzhalter-E-Mail Warnung
                            Placeholder::make('placeholder_email_warning')
                                ->label('')
                                ->visibleOn('edit')
                                ->visible(fn (Get $get): bool => ($get('email') === CorporateCustomer::PLACEHOLDER_EMAIL) && ($get('send_via_email') ?? true))
                                ->content(new HtmlString(
                                    '<div style="padding:12px 16px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;display:flex;align-items:flex-start;gap:12px;">'
                                    . '<span style="font-size:1.3rem;">⚠️</span>'
                                    . '<div>'
                                    . '<div style="font-weight:600;color:#b45309;">' . __('partner.corporate_customer.warnings.placeholder_email') . '</div>'
                                    . '<div style="font-size:0.85rem;color:#92400e;">' . __('partner.corporate_customer.warnings.placeholder_email_desc', ['email' => CorporateCustomer::PLACEHOLDER_EMAIL]) . '</div>'
                                    . '</div></div>'
                                ))
                                ->columnSpanFull(),

                            Section::make(__('partner.corporate_customer.sections.zuordnung'))
                                ->schema([
                                    Select::make('gas_station_id')
                                        ->label(__('partner.corporate_customer.fields.gas_station'))
                                        ->options(function () use ($tenantId) {
                                            return GasStation::where('tenant_id', $tenantId)->pluck('name', 'id');
                                        })
                                        ->required()
                                        ->searchable()
                                        ->preload(),

                                    TextInput::make('customer_number')
                                        ->label(__('partner.corporate_customer.fields.customer_number'))
                                        ->required()
                                        ->numeric()
                                        ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule, Get $get) {
                                            return $rule->where('gas_station_id', $get('gas_station_id'));
                                        }),
                                ])
                                ->columns(2),

                            Section::make(__('partner.corporate_customer.sections.kontaktdaten'))
                                ->schema([
                                    Select::make('salutation')
                                        ->label(__('partner.corporate_customer.fields.salutation'))
                                        ->options([
                                            'Herr' => __('partner.corporate_customer.salutations.Herr'),
                                            'Frau' => __('partner.corporate_customer.salutations.Frau'),
                                            'Firma' => __('partner.corporate_customer.salutations.Firma'),
                                            'Herr Dr.' => __('partner.corporate_customer.salutations.Herr Dr.'),
                                            'Frau Dr.' => __('partner.corporate_customer.salutations.Frau Dr.'),
                                            'Herr Dr. med.' => __('partner.corporate_customer.salutations.Herr Dr. med.'),
                                            'Herr Dr. dent.' => __('partner.corporate_customer.salutations.Herr Dr. dent.'),
                                            'Herrn' => __('partner.corporate_customer.salutations.Herrn'),
                                            'An die' => __('partner.corporate_customer.salutations.An die'),
                                        ])
                                        ->searchable(),

                                    TextInput::make('name')
                                        ->label(__('partner.corporate_customer.fields.name'))
                                        ->required()
                                        ->maxLength(255),

                                    TextInput::make('name_2')
                                        ->label(__('partner.corporate_customer.fields.name_2'))
                                        ->maxLength(255),

                                    TextInput::make('department')
                                        ->label(__('partner.corporate_customer.fields.department'))
                                        ->maxLength(255),

                                    TextInput::make('street')
                                        ->label(__('partner.corporate_customer.fields.street'))
                                        ->maxLength(255),

                                    TextInput::make('zip')
                                        ->label(__('partner.corporate_customer.fields.zip'))
                                        ->maxLength(10),

                                    TextInput::make('city')
                                        ->label(__('partner.corporate_customer.fields.city'))
                                        ->maxLength(255),

                                    TextInput::make('phone')
                                        ->label(__('partner.corporate_customer.fields.phone'))
                                        ->tel()
                                        ->maxLength(100),

                                    TextInput::make('email')
                                        ->label(__('partner.corporate_customer.fields.email'))
                                        ->email()
                                        ->maxLength(255)
                                        ->default(CorporateCustomer::PLACEHOLDER_EMAIL)
                                        ->helperText(__('partner.corporate_customer.fields.email_hint')),

                                    TextInput::make('notes')
                                        ->label(__('partner.corporate_customer.fields.notes'))
                                        ->maxLength(500)
                                        ->columnSpanFull(),
                                ])
                                ->columns(2),
                        ]),

                    // ========== TAB 2: BANKDATEN ==========
                    Tab::make(__('partner.corporate_customer.tabs.bankdaten'))
                        ->icon('heroicon-o-banknotes')
                        ->schema([
                            Section::make(__('partner.corporate_customer.sections.zahlungsart'))
                                ->schema([
                                    Select::make('payment_method')
                                        ->label(__('partner.corporate_customer.fields.payment_method'))
                                        ->options([
                                            'Bar' => __('partner.corporate_customer.payment_methods.Bar'),
                                            'SEPA - Basislastschrift' => __('partner.corporate_customer.payment_methods.SEPA - Basislastschrift'),
                                            'SEPA - Firmenlastschrift' => __('partner.corporate_customer.payment_methods.SEPA - Firmenlastschrift'),
                                        ])
                                        ->default('Bar')
                                        ->required()
                                        ->live(),
                                ])
                                ->columns(1),

                            Section::make(__('partner.corporate_customer.sections.sepa_bank'))
                                ->schema([
                                    TextInput::make('iban')
                                        ->label(__('partner.corporate_customer.fields.iban'))
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
                                                ->label(__('partner.corporate_customer.iban_calculator.label'))
                                                ->icon('heroicon-o-calculator')
                                                ->color('gray')
                                                ->size('sm')
                                                ->form([
                                                    TextInput::make('blz')
                                                        ->label(__('partner.corporate_customer.iban_calculator.blz'))
                                                        ->required()
                                                        ->maxLength(8)
                                                        ->placeholder(__('partner.corporate_customer.iban_calculator.blz_placeholder')),
                                                    TextInput::make('kontonummer')
                                                        ->label(__('partner.corporate_customer.iban_calculator.kontonummer'))
                                                        ->required()
                                                        ->maxLength(10)
                                                        ->placeholder(__('partner.corporate_customer.iban_calculator.kontonummer_placeholder')),
                                                ])
                                                ->modalHeading(__('partner.corporate_customer.iban_calculator.modal_heading'))
                                                ->modalSubmitActionLabel(__('partner.corporate_customer.iban_calculator.modal_submit'))
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
                                        ->label(__('partner.corporate_customer.fields.bic'))
                                        ->maxLength(11),

                                    TextInput::make('bank_name')
                                        ->label(__('partner.corporate_customer.fields.bank_name'))
                                        ->maxLength(255),

                                    TextInput::make('mandate_reference')
                                        ->label(__('partner.corporate_customer.fields.mandate_reference'))
                                        ->maxLength(100),

                                    TextInput::make('contract_number')
                                        ->label(__('partner.corporate_customer.fields.contract_number'))
                                        ->maxLength(100),
                                ])
                                ->columns(2)
                                ->visible(fn (Get $get): bool => str_starts_with($get('payment_method') ?? '', 'SEPA')),
                        ]),

                    // ========== TAB 3: KONDITIONEN ==========
                    Tab::make(__('partner.corporate_customer.tabs.konditionen'))
                        ->icon('heroicon-o-calculator')
                        ->schema([
                            Section::make(__('partner.corporate_customer.sections.finanzen'))
                                ->schema([
                                    TextInput::make('discount_percent')
                                        ->label(__('partner.corporate_customer.fields.discount_percent'))
                                        ->numeric()
                                        ->step(0.01)
                                        ->suffix('%')
                                        ->default(0.00),

                                    TextInput::make('credit_limit')
                                        ->label(__('partner.corporate_customer.fields.credit_limit'))
                                        ->numeric()
                                        ->step(0.01)
                                        ->prefix('EUR')
                                        ->default(0.00),

                                    TextInput::make('prepayment')
                                        ->label(__('partner.corporate_customer.fields.prepayment'))
                                        ->numeric()
                                        ->step(0.01)
                                        ->prefix('EUR')
                                        ->default(0.00),
                                ])
                                ->columns(3),

                            Section::make(__('partner.corporate_customer.sections.druckoptionen'))
                                ->schema([
                                    Toggle::make('print_delivery_note')
                                        ->label(__('partner.corporate_customer.fields.print_delivery_note'))
                                        ->default(false),

                                    Toggle::make('print_prepayment')
                                        ->label(__('partner.corporate_customer.fields.print_prepayment'))
                                        ->default(false),
                                ])
                                ->columns(2),

                            Section::make(__('partner.corporate_customer.sections.rechnungsversand'))
                                ->description(__('partner.corporate_customer.sections.rechnungsversand_desc'))
                                ->schema([
                                    Toggle::make('send_via_email')
                                        ->label(__('partner.corporate_customer.fields.send_via_email'))
                                        ->helperText(__('partner.corporate_customer.fields.send_via_email_hint'))
                                        ->default(true),

                                    Toggle::make('send_via_print')
                                        ->label(__('partner.corporate_customer.fields.send_via_print'))
                                        ->helperText(__('partner.corporate_customer.fields.send_via_print_hint'))
                                        ->default(false),

                                    Toggle::make('is_active')
                                        ->label(__('partner.corporate_customer.fields.is_active'))
                                        ->helperText(__('partner.corporate_customer.fields.is_active_hint'))
                                        ->default(true),
                                ])
                                ->columns(3),
                        ]),

                    // ========== TAB 4: TANKKARTEN ==========
                    Tab::make(__('partner.corporate_customer.tabs.tankkarten'))
                        ->icon('heroicon-o-credit-card')
                        ->schema([
                            Placeholder::make('fuel_cards_info')
                                ->label('')
                                ->content(function (?CorporateCustomer $record): HtmlString {
                                    if (! $record) {
                                        return new HtmlString('<span style="color:#6b7280;">' . __('partner.corporate_customer.fuel_cards_info.save_first') . '</span>');
                                    }
                                    $count = $record->fuelCards()->count();
                                    $icon = $count > 0 ? '💳' : '📭';

                                    return new HtmlString("<span style='font-size:0.9rem;'>{$icon} " . __('partner.corporate_customer.fuel_cards_info.count', ['count' => $count]) . "</span>");
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
                    ->label(__('partner.corporate_customer.fields.gas_station'))
                    ->description(fn ($record) => $record->gasStation?->station_number_shop ?? $record->gasStation?->station_number ?? '')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('customer_number')
                    ->label(__('partner.corporate_customer.table.customer_number'))
                    ->sortable()
                    ->searchable()
                    ->fontFamily('mono'),

                TextColumn::make('name')
                    ->label(__('partner.corporate_customer.fields.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (CorporateCustomer $record): ?string => $record->department),

                TextColumn::make('city')
                    ->label(__('partner.corporate_customer.fields.city'))
                    ->sortable()
                    ->toggleable(),

                BadgeColumn::make('payment_method')
                    ->label(__('partner.corporate_customer.fields.payment_method'))
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'Bar' => __('partner.corporate_customer.payment_methods.Bar'),
                        'SEPA - Basislastschrift' => __('partner.corporate_customer.payment_display.sepa_basis'),
                        'SEPA - Firmenlastschrift' => __('partner.corporate_customer.payment_display.sepa_firma'),
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Bar' => 'gray',
                        'SEPA - Basislastschrift' => 'info',
                        'SEPA - Firmenlastschrift' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('fuel_cards_count')
                    ->label(__('partner.corporate_customer.table.fuel_cards'))
                    ->counts('fuelCards')
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                TextColumn::make('email')
                    ->label(__('partner.corporate_customer.fields.email'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('credit_limit')
                    ->label(__('partner.corporate_customer.table.limit'))
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('imported_at')
                    ->label(__('partner.corporate_customer.table.imported_at'))
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('customer_number', 'asc')
            ->filters([
                SelectFilter::make('gas_station_id')
                    ->label(__('partner.corporate_customer.fields.gas_station'))
                    ->options(function () use ($tenantId) {
                        return GasStation::where('tenant_id', $tenantId)->pluck('name', 'id');
                    }),

                SelectFilter::make('payment_method')
                    ->label(__('partner.corporate_customer.fields.payment_method'))
                    ->options([
                        'Bar' => __('partner.corporate_customer.payment_methods.Bar'),
                        'SEPA - Basislastschrift' => __('partner.corporate_customer.payment_methods.SEPA - Basislastschrift'),
                        'SEPA - Firmenlastschrift' => __('partner.corporate_customer.payment_methods.SEPA - Firmenlastschrift'),
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
