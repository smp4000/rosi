<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\ArticleResource\Pages;
use App\Models\Article;
use App\Models\ArticleGroup;
use App\Models\GasStation;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Partner-Resource fuer Artikel-Verwaltung.
 * Tab-basiertes Design. Zeigt alle Artikel der eigenen Tankstellen.
 */
class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|\UnitEnum|null $navigationGroup = 'Tankstellen';

    protected static ?string $modelLabel = 'Artikel';

    protected static ?string $pluralModelLabel = 'Artikel';

    protected static ?int $navigationSort = 15;

    protected static ?string $recordTitleAttribute = 'name';

    // --- Autorisierung ---

    protected static function ensureTeamContext(): void
    {
        $user = auth()->user();
        if ($user?->tenant_id) {
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
        }
    }

    public static function canAccess(): bool
    {
        return true;
    }

    // --- Query: Nur Artikel der eigenen Tankstellen ---

    public static function getEloquentQuery(): Builder
    {
        $tenantId = auth()->user()?->tenant_id;

        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->whereHas('gasStation', function (Builder $q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            });
    }

    // --- Formular (Tab-Design) ---

    public static function form(Schema $schema): Schema
    {
        $tenantId = auth()->user()?->tenant_id;

        return $schema->components([
            Tabs::make('Artikel')
                ->tabs([
                    // ========== TAB: STAMMDATEN ==========
                    Tab::make('Stammdaten')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Select::make('gas_station_id')
                                ->label('Tankstelle')
                                ->options(
                                    GasStation::where('tenant_id', $tenantId)
                                        ->pluck('name', 'id')
                                )
                                ->required()
                                ->searchable(),

                            TextInput::make('article_number')
                                ->label('Artikelnummer')
                                ->required()
                                ->numeric(),

                            TextInput::make('name')
                                ->label('Bezeichnung')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),

                            TextInput::make('purchasing_price')
                                ->label('Einkaufspreis (EUR)')
                                ->numeric()
                                ->step(0.001)
                                ->default(0.000)
                                ->prefix('EUR'),

                            TextInput::make('selling_price')
                                ->label('Verkaufspreis (EUR)')
                                ->numeric()
                                ->step(0.01)
                                ->default(0.00)
                                ->prefix('EUR'),

                            Select::make('article_group_id')
                                ->label('Artikelgruppe')
                                ->options(
                                    ArticleGroup::forTenant($tenantId)
                                        ->orderBy('business_area_id')
                                        ->get()
                                        ->mapWithKeys(fn ($g) => [$g->id => "{$g->business_area} > {$g->article_group_04}"])
                                )
                                ->searchable()
                                ->nullable(),

                            TextInput::make('unit')
                                ->label('Einheit')
                                ->maxLength(50)
                                ->placeholder('St, l, kg...'),

                            Select::make('status')
                                ->label('Status')
                                ->options([
                                    'active' => 'Aktiv',
                                    'new' => 'Neu',
                                    'price_changed' => 'Preis geändert',
                                    'inactive' => 'Inaktiv',
                                ])
                                ->default('active'),
                        ])
                        ->columns(2),

                    // ========== TAB: BESTAND ==========
                    Tab::make('Bestand')
                        ->icon('heroicon-o-archive-box')
                        ->schema([
                            TextInput::make('min_stock')
                                ->label('Mindestbestand')
                                ->numeric()
                                ->default(0),

                            TextInput::make('max_stock')
                                ->label('Höchstbestand')
                                ->numeric()
                                ->default(1),

                            TextInput::make('current_stock')
                                ->label('Istbestand')
                                ->numeric()
                                ->default(0),

                            Select::make('modus')
                                ->label('Bestellmodus')
                                ->options([
                                    0 => '0 - Berechnen',
                                    1 => '1 - Fester Wert',
                                    2 => '2 - Nie vorschlagen',
                                    3 => '3 - Keine Bestellung möglich',
                                ])
                                ->default(0),
                        ])
                        ->columns(2),

                    // ========== TAB: LIEFERANT ==========
                    Tab::make('Lieferant')
                        ->icon('heroicon-o-truck')
                        ->schema([
                            TextInput::make('supplier_number')
                                ->label('Lieferant-Nr.')
                                ->numeric()
                                ->nullable(),

                            TextInput::make('supplier_name')
                                ->label('Lieferant-Name')
                                ->maxLength(255)
                                ->nullable(),

                            TextInput::make('order_number')
                                ->label('Bestellnummer')
                                ->maxLength(255)
                                ->nullable(),

                            TextInput::make('quantity_factor')
                                ->label('Mengenfaktor')
                                ->maxLength(50)
                                ->nullable(),
                        ])
                        ->columns(2),



                    // ========== TAB: IMPORT-INFO ==========
                    Tab::make('Import')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->schema([
                            Placeholder::make('csv_printed_at_display')
                                ->label('CSV-Druckdatum')
                                ->content(fn ($record) => $record?->csv_printed_at?->format('d.m.Y H:i') ?? '-'),

                            Placeholder::make('imported_at_display')
                                ->label('Importiert am')
                                ->content(fn ($record) => $record?->imported_at?->format('d.m.Y H:i') ?? '-'),

                            Placeholder::make('created_at_display')
                                ->label('Erstellt am')
                                ->content(fn ($record) => $record?->created_at?->format('d.m.Y H:i') ?? '-'),

                            Placeholder::make('updated_at_display')
                                ->label('Zuletzt geändert')
                                ->content(fn ($record) => $record?->updated_at?->format('d.m.Y H:i') ?? '-'),

                            // Aenderungshistorie
                            Placeholder::make('change_history')
                                ->label('Änderungshistorie')
                                ->content(function ($record) {
                                    if (! $record) {
                                        return 'Noch keine Änderungen.';
                                    }

                                    $changes = $record->changes()->latest()->limit(10)->get();

                                    if ($changes->isEmpty()) {
                                        return 'Keine Änderungen protokolliert.';
                                    }

                                    $html = '<div style="font-size:0.85rem;">';
                                    foreach ($changes as $change) {
                                        $date = $change->created_at->format('d.m.Y H:i');
                                        $type = match ($change->change_type) {
                                            'created' => '🆕 Erstellt',
                                            'price_updated' => '💰 Preis',
                                            'data_updated' => '📝 Daten',
                                            'reactivated' => '♻️ Reaktiviert',
                                            default => $change->change_type,
                                        };

                                        $detail = '';
                                        if ($change->field_name) {
                                            $detail = " ({$change->field_name}: {$change->old_value} → {$change->new_value})";
                                        }

                                        $html .= "<div style='margin-bottom:3px;'>{$type} - {$date}{$detail}</div>";
                                    }
                                    $html .= '</div>';

                                    return new \Illuminate\Support\HtmlString($html);
                                })
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->visibleOn('edit'),
                ])
                ->columnSpanFull(),
        ]);
    }

    // --- Tabelle ---

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('gasStation.name')
                    ->label('Tankstelle')
                    ->description(fn ($record) => $record->gasStation?->station_number_shop ?? $record->gasStation?->station_number ?? '')
                    ->sortable()
                    ->toggleable(),    

                TextColumn::make('article_number')
                    ->label('Art.-Nr.')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Bezeichnung')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(40),

                TextColumn::make('eans_display')
                    ->label('EAN')
                    ->html()
                    ->state(function ($record) {
                        $eans = \App\Models\ArticleEan::where('gas_station_id', $record->gas_station_id)
                            ->where('article_number', $record->article_number)
                            ->orderBy('ean')
                            ->pluck('ean');

                        if ($eans->isEmpty()) {
                            return '-';
                        }

                        $lines = $eans->take(2)->map(fn ($e) => e($e))->implode('<br>');
                        $remaining = $eans->count() - 2;
                        if ($remaining > 0) {
                            $lines .= '<br><span style="color:#9ca3af;">und ' . $remaining . ' weitere</span>';
                        }

                        return $lines;
                    })
                    ->searchable(query: function ($query, string $search) {
                        $query->whereExists(function ($sub) use ($search) {
                            $sub->selectRaw('1')
                                ->from('article_eans')
                                ->whereColumn('article_eans.article_number', 'articles.article_number')
                                ->whereColumn('article_eans.gas_station_id', 'articles.gas_station_id')
                                ->where('article_eans.ean', 'LIKE', "%{$search}%")
                                ->whereNull('article_eans.deleted_at');
                        });
                    })
                    ->toggleable(),

                TextColumn::make('articleGroup.article_group_04')
                    ->label('Artikelgruppe')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('unit')
                    ->label('Einh.')
                    ->toggleable(),

                TextColumn::make('purchasing_price')
                    ->label('EK')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('selling_price')
                    ->label('VK')
                    ->money('EUR')
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Aktiv',
                        'new' => 'Neu',
                        'price_changed' => 'Preis geändert',
                        'inactive' => 'Inaktiv',
                        'deleted' => 'Gelöscht',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'new' => 'info',
                        'price_changed' => 'warning',
                        'inactive' => 'gray',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('imported_at')
                    ->label('Importiert')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('article_number', 'asc')
            ->filters([
                SelectFilter::make('gas_station_id')
                    ->label('Tankstelle')
                    ->options(function () {
                        $tenantId = auth()->user()?->tenant_id;
                        return GasStation::where('tenant_id', $tenantId)->pluck('name', 'id');
                    }),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktiv',
                        'new' => 'Neu',
                        'price_changed' => 'Preis geändert',
                        'inactive' => 'Inaktiv',
                    ]),
                SelectFilter::make('article_group_id')
                    ->label('Artikelgruppe')
                    ->relationship('articleGroup', 'article_group_04')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
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

    public static function getRelations(): array
    {
        return [
            ArticleResource\RelationManagers\EansRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
