<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament Resource fuer die Mandanten-Verwaltung im Super-Admin-Panel.
 */
class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Mandanten-Verwaltung';

    protected static ?string $modelLabel = 'Mandant';

    protected static ?string $pluralModelLabel = 'Mandanten';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    // --- Autorisierung ---

    public static function canAccess(): bool
    {
        return auth()->user()->can('admin.tenants.list');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('admin.tenants.create');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->can('admin.tenants.edit');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->can('admin.tenants.delete');
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()->can('admin.tenants.view');
    }

    // --- Formular (Tabs) ---

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Mandant')
                ->tabs([
                    Tab::make(__('admin.tenant.tabs.stammdaten'))
                        ->icon('heroicon-o-building-office')
                        ->schema([
                            TextInput::make('name')
                                ->label(__('admin.tenant.fields.name'))
                                ->required()
                                ->maxLength(255),
                            TextInput::make('slug')
                                ->label(__('admin.tenant.fields.slug'))
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255)
                                ->helperText('Wird automatisch aus dem Firmennamen generiert.'),
                            Select::make('owner_id')
                                ->label(__('admin.tenant.fields.owner'))
                                ->relationship('owner', 'last_name')
                                ->searchable()
                                ->preload(),
                            TextInput::make('email')
                                ->label(__('admin.tenant.fields.email'))
                                ->email()
                                ->required()
                                ->maxLength(255),
                            TextInput::make('phone')
                                ->label(__('admin.tenant.fields.phone'))
                                ->tel()
                                ->maxLength(50),
                        ]),

                    Tab::make(__('admin.tenant.tabs.adresse'))
                        ->icon('heroicon-o-map-pin')
                        ->schema([
                            TextInput::make('street')
                                ->label(__('admin.tenant.fields.street'))
                                ->maxLength(255),
                            TextInput::make('zip')
                                ->label(__('admin.tenant.fields.zip'))
                                ->maxLength(10),
                            TextInput::make('city')
                                ->label(__('admin.tenant.fields.city'))
                                ->maxLength(255),
                            TextInput::make('country')
                                ->label(__('admin.tenant.fields.country'))
                                ->default('DE')
                                ->maxLength(2),
                        ]),

                    Tab::make(__('admin.tenant.tabs.abonnement'))
                        ->icon('heroicon-o-credit-card')
                        ->schema([
                            Select::make('subscription_status')
                                ->label(__('admin.tenant.fields.subscription_status'))
                                ->options(__('admin.tenant.subscription_statuses'))
                                ->required(),
                            TextInput::make('subscription_plan')
                                ->label(__('admin.tenant.fields.subscription_plan'))
                                ->maxLength(50),
                            DateTimePicker::make('trial_ends_at')
                                ->label(__('admin.tenant.fields.trial_ends_at'))
                                ->displayFormat('d.m.Y H:i'),
                            Toggle::make('is_active')
                                ->label(__('admin.tenant.fields.is_active'))
                                ->default(true),
                        ]),

                    Tab::make(__('admin.tenant.tabs.geschaeftsdaten'))
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            TextInput::make('tax_id')
                                ->label(__('admin.tenant.fields.tax_id'))
                                ->maxLength(50),
                            TextInput::make('trade_register')
                                ->label(__('admin.tenant.fields.trade_register'))
                                ->maxLength(100),
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
                    ->label(__('admin.tenant.fields.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('owner.last_name')
                    ->label(__('admin.tenant.fields.owner'))
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->owner?->name ?? '-'),

                TextColumn::make('email')
                    ->label(__('admin.tenant.fields.email'))
                    ->searchable()
                    ->toggleable(),

                BadgeColumn::make('subscription_status')
                    ->label(__('admin.tenant.fields.subscription_status'))
                    ->formatStateUsing(fn (string $state) => __("admin.tenant.subscription_statuses.{$state}"))
                    ->colors([
                        'warning' => 'trial',
                        'success' => 'active',
                        'danger' => 'expired',
                        'gray' => 'cancelled',
                    ])
                    ->sortable(),

                TextColumn::make('trial_ends_at')
                    ->label(__('admin.tenant.fields.trial_ends_at'))
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable()
                    ->placeholder('-'),

                TextColumn::make('users_count')
                    ->label(__('admin.tenant.fields.users_count'))
                    ->counts('users')
                    ->sortable(),

                TextColumn::make('gas_stations_count')
                    ->label(__('admin.tenant.fields.gas_stations_count'))
                    ->counts('gasStations')
                    ->sortable(),

                BooleanColumn::make('is_active')
                    ->label(__('admin.tenant.fields.is_active'))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('admin.tenant.fields.created_at'))
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('subscription_status')
                    ->label(__('admin.tenant.fields.subscription_status'))
                    ->options(__('admin.tenant.subscription_statuses')),

                TernaryFilter::make('is_active')
                    ->label(__('admin.tenant.fields.is_active'))
                    ->trueLabel('Nur aktive')
                    ->falseLabel('Nur gesperrte'),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),

                // Sperren/Entsperren Toggle
                Action::make('toggleActive')
                    ->label(fn (Tenant $record) => $record->is_active
                        ? __('admin.tenant.actions.lock')
                        : __('admin.tenant.actions.unlock'))
                    ->icon(fn (Tenant $record) => $record->is_active
                        ? 'heroicon-o-lock-closed'
                        : 'heroicon-o-lock-open')
                    ->color(fn (Tenant $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (Tenant $record) => $record->update(['is_active' => ! $record->is_active])),
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
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'view' => Pages\ViewTenant::route('/{record}'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'slug'];
    }
}
