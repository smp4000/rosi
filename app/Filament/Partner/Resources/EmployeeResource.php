<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\EmployeeResource\Pages;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament Resource fuer die Mitarbeiter-Verwaltung im Partner-Panel.
 * Zeigt nur Benutzer vom Typ 'employee' des eigenen Mandanten.
 * DSGVO: Keine verschluesselten EmployeeProfile-Felder sichtbar!
 */
class EmployeeResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Personal';

    protected static ?string $modelLabel = 'Mitarbeiter';

    protected static ?string $pluralModelLabel = 'Mitarbeiter';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'email';

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
        return false;
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
            ->with(['employeeProfile', 'gasStations']);
    }

    // --- Formular (Tabs) ---

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Mitarbeiter')
                ->tabs([
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
                            Toggle::make('is_active')
                                ->label(__('partner.employee.fields.is_active')),
                        ]),

                    Tab::make(__('partner.employee.tabs.beschaeftigung'))
                        ->icon('heroicon-o-briefcase')
                        ->schema([
                            Select::make('employeeProfile.employment_type')
                                ->label(__('partner.employee.fields.employment_type'))
                                ->options(__('partner.employee.employment_types')),
                            DatePicker::make('employeeProfile.employment_start')
                                ->label(__('partner.employee.fields.employment_start'))
                                ->displayFormat('d.m.Y'),
                            DatePicker::make('employeeProfile.employment_end')
                                ->label(__('partner.employee.fields.employment_end'))
                                ->displayFormat('d.m.Y'),
                            TextInput::make('employeeProfile.weekly_hours')
                                ->label(__('partner.employee.fields.weekly_hours'))
                                ->numeric()
                                ->step(0.5)
                                ->minValue(0)
                                ->maxValue(48),
                            TextInput::make('employeeProfile.vacation_days')
                                ->label(__('partner.employee.fields.vacation_days'))
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(60),
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
            'view' => Pages\ViewEmployee::route('/{record}'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
