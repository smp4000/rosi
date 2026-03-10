<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Actions;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament Resource fuer die globale Benutzerverwaltung im Super-Admin-Panel.
 * Zeigt KEINE sensiblen EmployeeProfile-Daten (DSGVO).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Benutzerverwaltung';

    protected static ?string $modelLabel = 'Benutzer';

    protected static ?string $pluralModelLabel = 'Benutzer';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'email';

    // --- Autorisierung ---

    public static function canAccess(): bool
    {
        return auth()->user()->can('admin.users.list');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->can('admin.users.create');
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()->can('admin.users.edit');
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()->can('admin.users.edit');
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()->can('admin.users.view');
    }

    // --- Query mit Soft Deletes und Tenant eager-loading ---

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('tenant')
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    // --- Formular (Tabs) ---

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Benutzer')
                ->tabs([
                    Tab::make('Stammdaten')
                        ->icon('heroicon-o-user')
                        ->schema([
                            TextInput::make('first_name')
                                ->label(__('admin.user.fields.first_name'))
                                ->maxLength(255),
                            TextInput::make('last_name')
                                ->label(__('admin.user.fields.last_name'))
                                ->required()
                                ->maxLength(255),
                            TextInput::make('email')
                                ->label(__('admin.user.fields.email'))
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),
                            Select::make('type')
                                ->label(__('admin.user.fields.type'))
                                ->options(__('admin.user.types'))
                                ->disabled()
                                ->dehydrated(),
                            Select::make('tenant_id')
                                ->label(__('admin.user.fields.tenant'))
                                ->relationship('tenant', 'name')
                                ->searchable()
                                ->preload()
                                ->nullable(),
                        ])
                        ->columns(2),

                    Tab::make('Status')
                        ->icon('heroicon-o-signal')
                        ->schema([
                            Toggle::make('is_active')
                                ->label(__('admin.user.fields.is_active')),
                            Placeholder::make('last_login_at_display')
                                ->label(__('admin.user.fields.last_login_at'))
                                ->content(fn (?Model $record) => $record?->last_login_at?->format('d.m.Y H:i') ?? 'Nie'),
                            Placeholder::make('created_at_display')
                                ->label(__('admin.user.fields.created_at'))
                                ->content(fn (?Model $record) => $record?->created_at?->format('d.m.Y H:i') ?? '-'),
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
                    ->label(__('admin.user.fields.name'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->formatStateUsing(fn ($record) => $record->name)
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label(__('admin.user.fields.email'))
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('type')
                    ->label(__('admin.user.fields.type'))
                    ->formatStateUsing(fn (string $state) => __("admin.user.types.{$state}"))
                    ->colors([
                        'danger' => 'super_admin',
                        'primary' => 'partner',
                        'info' => 'employee',
                        'gray' => 'customer',
                    ])
                    ->sortable(),

                TextColumn::make('tenant.name')
                    ->label(__('admin.user.fields.tenant'))
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),

                BooleanColumn::make('is_active')
                    ->label(__('admin.user.fields.is_active'))
                    ->sortable(),

                TextColumn::make('last_login_at')
                    ->label(__('admin.user.fields.last_login_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('Nie'),

                TextColumn::make('created_at')
                    ->label(__('admin.user.fields.created_at'))
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label(__('admin.user.fields.type'))
                    ->options(__('admin.user.types')),

                SelectFilter::make('tenant_id')
                    ->label(__('admin.user.fields.tenant'))
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_active')
                    ->label(__('admin.user.fields.is_active'))
                    ->trueLabel('Nur aktive')
                    ->falseLabel('Nur inaktive'),

                TrashedFilter::make(),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    // --- Seiten ---

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'email'];
    }
}
