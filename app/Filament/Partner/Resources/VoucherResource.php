<?php

namespace App\Filament\Partner\Resources;

use App\Models\Voucher;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Alle Gutscheine';

    protected static ?string $modelLabel = 'Gutschein';

    protected static ?string $pluralModelLabel = 'Gutscheine';

    protected static string|\UnitEnum|null $navigationGroup = 'Gutscheine';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $form): Schema
    {
        return $form
            ->components([
                Forms\Components\Section::make('Gutschein-Details')
                    ->schema([
                        Forms\Components\TextInput::make('voucher_number')
                            ->label('Gutscheinnummer')
                            ->disabled(),

                        Forms\Components\TextInput::make('voucher_group')
                            ->label('Gruppe')
                            ->disabled(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Originalwert')
                            ->suffix('EUR')
                            ->disabled(),

                        Forms\Components\TextInput::make('remaining_amount')
                            ->label('Restguthaben')
                            ->suffix('EUR')
                            ->disabled(),

                        Forms\Components\TextInput::make('status')
                            ->label('Status')
                            ->disabled(),

                        Forms\Components\DatePicker::make('issued_at')
                            ->label('Ausgabedatum')
                            ->disabled(),

                        Forms\Components\DatePicker::make('valid_until')
                            ->label('Gueltig bis')
                            ->disabled(),

                        Forms\Components\TextInput::make('employee_name')
                            ->label('Ausgegeben von')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('voucher_number')
                    ->label('Nummer')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('voucher_group')
                    ->label('Gruppe')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Wert')
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Restguthaben')
                    ->money('EUR')
                    ->sortable()
                    ->color(fn (Voucher $record) => $record->remaining_amount < $record->amount ? 'warning' : null),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => Voucher::STATUS_ACTIVE,
                        'warning' => Voucher::STATUS_PARTIALLY_REDEEMED,
                        'gray' => Voucher::STATUS_REDEEMED,
                        'danger' => Voucher::STATUS_EXPIRED,
                        'danger' => Voucher::STATUS_CANCELLED,
                    ])
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Voucher::STATUS_ACTIVE => 'Aktiv',
                        Voucher::STATUS_PARTIALLY_REDEEMED => 'Teileingeloest',
                        Voucher::STATUS_REDEEMED => 'Eingeloest',
                        Voucher::STATUS_EXPIRED => 'Abgelaufen',
                        Voucher::STATUS_CANCELLED => 'Storniert',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Ausgabe')
                    ->date('d.m.Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Gueltig bis')
                    ->date('d.m.Y')
                    ->sortable()
                    ->color(fn (Voucher $record) => $record->valid_until < now() ? 'danger' : null),

                Tables\Columns\TextColumn::make('employee_name')
                    ->label('Mitarbeiter')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('redemptions_count')
                    ->label('Einloesungen')
                    ->counts('redemptions')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        Voucher::STATUS_ACTIVE => 'Aktiv',
                        Voucher::STATUS_PARTIALLY_REDEEMED => 'Teileingeloest',
                        Voucher::STATUS_REDEEMED => 'Eingeloest',
                        Voucher::STATUS_EXPIRED => 'Abgelaufen',
                        Voucher::STATUS_CANCELLED => 'Storniert',
                    ]),

                Tables\Filters\Filter::make('voucher_group')
                    ->form([
                        Forms\Components\TextInput::make('voucher_group')
                            ->label('Gruppe')
                            ->placeholder('z.B. 4567'),
                    ])
                    ->query(fn ($query, array $data) => $data['voucher_group']
                        ? $query->where('voucher_group', $data['voucher_group'])
                        : $query
                    ),

                Tables\Filters\Filter::make('valid')
                    ->label('Nur gueltige')
                    ->query(fn ($query) => $query->where('valid_until', '>=', now()->toDateString())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('redeem')
                    ->label('Einloesen')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Voucher $record) => $record->isRedeemable())
                    ->form([
                        Forms\Components\TextInput::make('redeem_amount')
                            ->label('Einloesungsbetrag (EUR)')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->helperText(fn (Voucher $record) => "Restguthaben: {$record->formatted_remaining}"),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notizen')
                            ->rows(2),
                    ])
                    ->action(function (Voucher $record, array $data) {
                        try {
                            $user = auth()->user();
                            $stationId = session('station_id')
                                ?? \App\Models\GasStation::where('tenant_id', $user->tenant_id)->first()?->id;
                            $record->redeem(
                                redeemAmount: (float) $data['redeem_amount'],
                                stationId: $stationId,
                                employeeId: $user->id,
                                employeeName: $user->name,
                                notes: $data['notes'] ?? null,
                            );

                            \Filament\Notifications\Notification::make()
                                ->title('Gutschein eingeloest')
                                ->body("{$data['redeem_amount']} EUR von {$record->voucher_number}")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            \Filament\Notifications\Notification::make()
                                ->title('Einloesung fehlgeschlagen')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('Stornieren')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Voucher $record) => in_array($record->status, [Voucher::STATUS_ACTIVE]))
                    ->requiresConfirmation()
                    ->modalHeading('Gutschein stornieren')
                    ->modalDescription(fn (Voucher $record) => "Gutschein {$record->voucher_number} ({$record->formatted_amount}) wirklich stornieren?")
                    ->action(function (Voucher $record) {
                        $record->update(['status' => Voucher::STATUS_CANCELLED]);
                        \Filament\Notifications\Notification::make()
                            ->title('Gutschein storniert')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Partner\Resources\VoucherResource\RelationManagers\RedemptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Partner\Resources\VoucherResource\Pages\ListVouchers::route('/'),
            'view' => \App\Filament\Partner\Resources\VoucherResource\Pages\ViewVoucher::route('/{record}'),
        ];
    }
}
