<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\DeviceInvitationResource\Pages;
use App\Models\DeviceInvitation;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Filament Resource: POS-Einladungen verwalten.
 * Partner sieht alle versendeten Einladungen und deren Status.
 */
class DeviceInvitationResource extends Resource
{
    use \App\Filament\Concerns\HasCatalogPermissions;

    /** Katalog-Schluessel fuer die Rechte-Pruefung (Rollen-Matrix) */
    protected static ?string $permissionKey = 'partner.devices';

    protected static ?string $model = DeviceInvitation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|\UnitEnum|null $navigationGroup = 'POS App';

    protected static ?string $modelLabel = 'Einladung';

    protected static ?string $pluralModelLabel = 'Einladungen';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Mitarbeiter')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('station.name')
                    ->label('Tankstelle')
                    ->searchable(),

                TextColumn::make('channel')
                    ->label('Versand')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'email' => 'E-Mail',
                        'sms' => 'SMS',
                        'whatsapp' => 'WhatsApp',
                        'qr_code' => 'QR-Code',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'email' => 'info',
                        'sms' => 'warning',
                        'whatsapp' => 'success',
                        'qr_code' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => 'Ausstehend',
                        'accepted' => 'Angenommen',
                        'expired' => 'Abgelaufen',
                        'revoked' => 'Widerrufen',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'accepted' => 'success',
                        'expired' => 'gray',
                        'revoked' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('invitedBy.name')
                    ->label('Eingeladen von'),

                TextColumn::make('expires_at')
                    ->label('Gueltig bis')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('accepted_at')
                    ->label('Angenommen am')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->date('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Ausstehend',
                        'accepted' => 'Angenommen',
                        'expired' => 'Abgelaufen',
                        'revoked' => 'Widerrufen',
                    ]),

                SelectFilter::make('channel')
                    ->label('Versandkanal')
                    ->options([
                        'email' => 'E-Mail',
                        'sms' => 'SMS',
                        'whatsapp' => 'WhatsApp',
                        'qr_code' => 'QR-Code',
                    ]),
            ])
            ->actions([
                // Link kopieren (nur bei ausstehenden)
                Action::make('copy_link')
                    ->label('Link kopieren')
                    ->icon('heroicon-o-clipboard')
                    ->color('gray')
                    ->visible(fn (DeviceInvitation $record) => $record->status === 'pending')
                    ->action(function (DeviceInvitation $record) {
                        $url = url("/pos-setup/{$record->token}");

                        \Filament\Notifications\Notification::make()
                            ->title('Einladungslink')
                            ->body($url)
                            ->success()
                            ->persistent()
                            ->send();
                    }),

                // QR-Code nochmal anzeigen
                Action::make('show_qr')
                    ->label('QR-Code')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->visible(fn (DeviceInvitation $record) => $record->status === 'pending' && $record->channel === 'qr_code')
                    ->action(function (DeviceInvitation $record) {
                        $qrSvg = QrCode::size(300)->margin(2)->generate($record->token);
                        $qrBase64 = base64_encode($qrSvg);

                        session()->flash('show_qr_code', true);
                        session()->flash('qr_base64', $qrBase64);
                        session()->flash('qr_station', $record->station->name ?? 'Station');
                        session()->flash('qr_token', $record->token);
                        session()->flash('qr_expires', $record->expires_at->format('d.m.Y H:i'));
                    }),

                // Einladung widerrufen
                Action::make('revoke')
                    ->label('Widerrufen')
                    ->icon('heroicon-o-x-mark')
                    ->color('warning')
                    ->visible(fn (DeviceInvitation $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Einladung widerrufen?')
                    ->modalDescription('Der Einladungslink wird ungueltig.')
                    ->action(fn (DeviceInvitation $record) => $record->update(['status' => 'revoked'])),

                // Einladung loeschen
                Action::make('delete')
                    ->label('Loeschen')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Einladung wirklich loeschen?')
                    ->modalDescription('Diese Einladung wird unwiderruflich geloescht. Sind Sie sicher?')
                    ->modalSubmitActionLabel('Ja, loeschen')
                    ->action(fn (DeviceInvitation $record) => $record->delete()),
            ])
            ->bulkActions([
                BulkAction::make('bulk_delete')
                    ->label('Ausgewaehlte loeschen')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Ausgewaehlte Einladungen loeschen?')
                    ->modalDescription('Alle ausgewaehlten Einladungen werden unwiderruflich geloescht. Sind Sie sicher?')
                    ->modalSubmitActionLabel('Ja, alle loeschen')
                    ->action(fn (Collection $records) => $records->each->delete())
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Noch keine Einladungen')
            ->emptyStateDescription('Erstellen Sie Einladungen ueber die Geraete-Seite.')
            ->emptyStateIcon('heroicon-o-envelope');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeviceInvitations::route('/'),
        ];
    }
}
