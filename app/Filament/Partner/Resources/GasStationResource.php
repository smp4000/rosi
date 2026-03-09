<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\GasStationResource\Pages;
use App\Models\GasStation;
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
                                ->maxLength(255),
                            TextInput::make('brand')
                                ->label(__('partner.gas_station.fields.brand'))
                                ->maxLength(100),
                            TextInput::make('station_number')
                                ->label(__('partner.gas_station.fields.station_number'))
                                ->maxLength(50),
                            Toggle::make('is_active')
                                ->label(__('partner.gas_station.fields.is_active'))
                                ->default(true),
                        ]),

                    Tab::make(__('partner.gas_station.tabs.adresse'))
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            TextInput::make('street')
                                ->label(__('partner.gas_station.fields.street'))
                                ->maxLength(255),
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
                                ->numeric(),
                            TextInput::make('longitude')
                                ->label(__('partner.gas_station.fields.longitude'))
                                ->numeric(),
                        ]),

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
                        ]),

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
                            TextInput::make('notes')
                                ->label(__('partner.gas_station.fields.notes'))
                                ->maxLength(1000),
                        ]),
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

                TextColumn::make('brand')
                    ->label(__('partner.gas_station.fields.brand'))
                    ->sortable()
                    ->placeholder('-')
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
