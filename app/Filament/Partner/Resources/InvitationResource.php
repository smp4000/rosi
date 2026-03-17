<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\InvitationResource\Pages;
use App\Models\GasStation;
use App\Models\Invitation;
use App\Services\OnboardingService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

/**
 * Einladungen-Verwaltung im Partner-Panel.
 * Partner koennen Mitarbeiter per E-Mail einladen.
 */
class InvitationResource extends Resource
{
    protected static ?string $model = Invitation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|\UnitEnum|null $navigationGroup = 'Personal';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Einladung';

    protected static ?string $pluralModelLabel = 'Einladungen';

    protected static ?string $recordTitleAttribute = 'email';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user && $user->can('partner.invitations.list');
    }

    public static function form(Schema $form): Schema
    {
        $tenantId = session('tenant_id');

        return $form->schema([
            TextInput::make('email')
                ->label(__('partner.invitation.fields.email'))
                ->email()
                ->required()
                ->maxLength(255),

            Select::make('type')
                ->label(__('partner.invitation.fields.type'))
                ->options(__('partner.invitation.types'))
                ->default('employee')
                ->required(),

            Select::make('role_id')
                ->label(__('partner.invitation.fields.role'))
                ->options(function () use ($tenantId) {
                    if (! $tenantId) {
                        return [];
                    }
                    app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId);

                    return Role::where('guard_name', 'web')
                        ->whereIn('name', ['partner', 'stationsleiter', 'mitarbeiter'])
                        ->pluck('name', 'id')
                        ->map(fn ($name) => match ($name) {
                            'partner' => 'Partner (Voller Zugriff)',
                            'stationsleiter' => 'Stationsleiter',
                            'mitarbeiter' => 'Mitarbeiter',
                            default => $name,
                        });
                })
                ->placeholder('Standard: Mitarbeiter'),

            CheckboxList::make('gas_station_ids')
                ->label(__('partner.invitation.fields.gas_stations'))
                ->options(function () use ($tenantId) {
                    return GasStation::where('tenant_id', $tenantId)
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id');
                })
                ->columns(2),

            Textarea::make('message')
                ->label(__('partner.invitation.fields.message'))
                ->rows(3)
                ->placeholder('Optionale persoenliche Nachricht an den Mitarbeiter...'),

            DateTimePicker::make('expires_at')
                ->label(__('partner.invitation.fields.expires_at'))
                ->default(now()->addDays(7))
                ->minDate(now())
                ->required()
                ->format('d.m.Y H:i'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->label(__('partner.invitation.fields.email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('partner.invitation.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __("partner.invitation.types.{$state}"))
                    ->color(fn (string $state) => match ($state) {
                        'manager' => 'primary',
                        default => 'info',
                    }),

                TextColumn::make('status')
                    ->label(__('partner.invitation.fields.status'))
                    ->badge()
                    ->getStateUsing(function (Invitation $record) {
                        if ($record->isAccepted()) {
                            return 'accepted';
                        }
                        if ($record->isExpired()) {
                            return 'expired';
                        }

                        return 'pending';
                    })
                    ->formatStateUsing(fn (string $state) => __("partner.invitation.statuses.{$state}"))
                    ->color(fn (string $state) => match ($state) {
                        'accepted' => 'success',
                        'expired' => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('expires_at')
                    ->label(__('partner.invitation.fields.expires_at'))
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('accepted_at')
                    ->label(__('partner.invitation.fields.accepted_at'))
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Erstellt am')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Actions\Action::make('resend')
                    ->label(__('partner.invitation.actions.resend'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Invitation $record) => $record->isValid())
                    ->action(function (Invitation $record) {
                        app(OnboardingService::class)->resendInvitation($record);
                        Notification::make()
                            ->title(__('partner.invitation.messages.resent'))
                            ->success()
                            ->send();
                    }),

                Actions\DeleteAction::make()
                    ->label(__('partner.invitation.actions.cancel'))
                    ->visible(fn (Invitation $record) => ! $record->isAccepted()),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', session('tenant_id'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvitations::route('/'),
            'create' => Pages\CreateInvitation::route('/create'),
        ];
    }
}
