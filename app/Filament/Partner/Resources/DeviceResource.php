<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\DeviceResource\Pages;
use App\Models\Device;
use App\Models\DeviceInvitation;
use App\Models\GasStation;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Filament Resource: POS-Geraete verwalten.
 * Unterscheidung zwischen Stations-Geraeten (MDE/Firmen) und Mitarbeiter-Handys.
 */
class DeviceResource extends Resource
{
    protected static ?string $model = Device::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static string|\UnitEnum|null $navigationGroup = 'POS App';

    protected static ?string $modelLabel = 'Geraet';

    protected static ?string $pluralModelLabel = 'Geraete';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // ── Kategorie: Stations-Geraet oder Mitarbeiter-Handy ──
                TextColumn::make('device_type')
                    ->label('Kategorie')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'mde' => 'Stations-Geraet',
                        'personal' => 'Mitarbeiter-Handy',
                        default => $state,
                    })
                    ->icon(fn (string $state) => match ($state) {
                        'mde' => 'heroicon-o-building-storefront',
                        'personal' => 'heroicon-o-user',
                        default => 'heroicon-o-device-phone-mobile',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'mde' => 'info',
                        'personal' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('station.name')
                    ->label('Tankstelle')
                    ->searchable()
                    ->sortable(),

                // ── Mitarbeiter: nur bei persoenlichen Geraeten relevant ──
                TextColumn::make('user.name')
                    ->label('Mitarbeiter')
                    ->placeholder('—')
                    ->visible(fn () => true)
                    ->description(fn (Device $record) => $record->device_type === 'mde'
                        ? 'Kein Mitarbeiter (Stations-Geraet)'
                        : null)
                    ->color(fn (Device $record) => $record->device_type === 'mde'
                        ? 'gray'
                        : null)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('device_name')
                    ->label('Geraet')
                    ->placeholder('Unbekannt'),

                TextColumn::make('device_os')
                    ->label('System')
                    ->placeholder('—'),

                TextColumn::make('app_version')
                    ->label('Version')
                    ->placeholder('—'),

                BooleanColumn::make('is_active')
                    ->label('Aktiv'),

                TextColumn::make('last_seen_at')
                    ->label('Zuletzt online')
                    ->since()
                    ->placeholder('Noch nie'),

                TextColumn::make('created_at')
                    ->label('Registriert am')
                    ->date('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('station_id')
                    ->label('Tankstelle')
                    ->relationship('station', 'name'),

                SelectFilter::make('device_type')
                    ->label('Kategorie')
                    ->options([
                        'mde' => 'Stations-Geraete',
                        'personal' => 'Mitarbeiter-Handys',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Aktiv')
                    ->trueLabel('Nur aktive')
                    ->falseLabel('Nur deaktivierte'),
            ])
            ->groups([
                Group::make('device_type')
                    ->label('Kategorie')
                    ->getTitleFromRecordUsing(fn (Device $record) => match ($record->device_type) {
                        'mde' => 'Stations-Geraete (MDE / Firmengeraete)',
                        'personal' => 'Mitarbeiter-Handys',
                        default => 'Sonstige',
                    }),
                Group::make('station.name')
                    ->label('Tankstelle'),
            ])
            ->defaultGroup('device_type')
            ->actions([
                // ── QR-Code / Setup-Code nochmal anzeigen ──
                Action::make('show_code')
                    ->label('Code anzeigen')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->modalHeading(fn (Device $record) => 'Setup-Code: ' . ($record->station->name ?? 'Station'))
                    ->modalContent(function (Device $record) {
                        // Letzte akzeptierte Einladung fuer dieses Geraet suchen
                        $invitation = DeviceInvitation::where('device_id', $record->id)
                            ->where('status', 'accepted')
                            ->latest()
                            ->first();

                        $token = $invitation?->token;
                        $stationName = $record->station->name ?? 'Station';
                        $deviceType = $record->device_type === 'mde' ? 'Stations-Geraet' : 'Mitarbeiter-Handy';
                        $userName = $record->user?->name ?? '—';
                        $registeredAt = $record->created_at?->format('d.m.Y H:i') ?? '—';

                        if ($token) {
                            $qrSvg = QrCode::size(250)->margin(2)->generate($token);
                            $qrBase64 = base64_encode($qrSvg);
                            $qrHtml = '<div style="text-align:center;margin:16px 0">'
                                . '<img src="data:image/svg+xml;base64,' . $qrBase64 . '" style="width:250px;height:250px;margin:0 auto;display:block" />'
                                . '</div>'
                                . '<div style="margin:8px 0;padding:8px;background:#f5f5f5;border-radius:6px;font-family:monospace;font-size:11px;word-break:break-all;text-align:center">'
                                . e($token)
                                . '</div>';
                        } else {
                            $qrHtml = '<div style="text-align:center;padding:24px;color:#999">'
                                . '<p>Kein Setup-Token vorhanden.</p>'
                                . '<p style="font-size:12px">Das Geraet wurde moeglicherweise ohne Einladung registriert.</p>'
                                . '</div>';
                        }

                        return new HtmlString(
                            '<div style="padding:8px">'
                            . '<table style="width:100%;margin-bottom:12px;font-size:13px">'
                            . '<tr><td style="color:#888;padding:4px 8px">Kategorie:</td><td style="font-weight:600;padding:4px 8px">' . e($deviceType) . '</td></tr>'
                            . '<tr><td style="color:#888;padding:4px 8px">Tankstelle:</td><td style="font-weight:600;padding:4px 8px">' . e($stationName) . '</td></tr>'
                            . '<tr><td style="color:#888;padding:4px 8px">Mitarbeiter:</td><td style="font-weight:600;padding:4px 8px">' . e($userName) . '</td></tr>'
                            . '<tr><td style="color:#888;padding:4px 8px">Geraet:</td><td style="font-weight:600;padding:4px 8px">' . e($record->device_name ?? '—') . '</td></tr>'
                            . '<tr><td style="color:#888;padding:4px 8px">Registriert:</td><td style="font-weight:600;padding:4px 8px">' . e($registeredAt) . '</td></tr>'
                            . '</table>'
                            . $qrHtml
                            . '</div>'
                        );
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Schliessen'),

                // ── QR-Code / Link teilen ──
                Action::make('share_code')
                    ->label('Teilen')
                    ->icon('heroicon-o-share')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\Select::make('channel')
                            ->label('Teilen per')
                            ->options([
                                'copy' => 'Link kopieren',
                                'email' => 'E-Mail',
                                'whatsapp' => 'WhatsApp',
                            ])
                            ->default('copy')
                            ->required(),
                    ])
                    ->action(function (Device $record, array $data) {
                        // Neuen Einladungstoken fuer diese Station generieren
                        $token = DeviceInvitation::generateToken();
                        $stationName = $record->station->name ?? 'Station';

                        $invitation = DeviceInvitation::create([
                            'tenant_id' => $record->tenant_id,
                            'station_id' => $record->station_id,
                            'user_id' => $record->user_id ?? auth()->id(),
                            'invited_by' => auth()->id(),
                            'token' => $token,
                            'channel' => $data['channel'] === 'copy' ? 'qr_code' : $data['channel'],
                            'status' => 'pending',
                            'expires_at' => now()->addHours(24),
                        ]);

                        $setupUrl = url("/pos-setup/{$token}");
                        $message = "ROSI POS Einrichtung fuer {$stationName}\n\n"
                            . "Scanne den QR-Code oder gib diesen Code in der App ein:\n"
                            . "{$token}\n\n"
                            . "Oder oeffne diesen Link:\n{$setupUrl}\n\n"
                            . "Gueltig bis: " . now()->addHours(24)->format('d.m.Y H:i') . " Uhr";

                        if ($data['channel'] === 'whatsapp') {
                            $waUrl = 'https://wa.me/?text=' . urlencode($message);

                            \Filament\Notifications\Notification::make()
                                ->title('WhatsApp oeffnen')
                                ->body(new HtmlString(
                                    '<a href="' . $waUrl . '" target="_blank" '
                                    . 'style="color:#25D366;font-weight:bold;text-decoration:underline">'
                                    . 'Hier klicken um WhatsApp zu oeffnen</a>'
                                ))
                                ->success()
                                ->persistent()
                                ->send();
                        } elseif ($data['channel'] === 'email') {
                            $subject = urlencode("ROSI POS Einrichtung - {$stationName}");
                            $body = urlencode($message);
                            $mailtoUrl = "mailto:?subject={$subject}&body={$body}";

                            \Filament\Notifications\Notification::make()
                                ->title('E-Mail vorbereitet')
                                ->body(new HtmlString(
                                    '<a href="' . $mailtoUrl . '" '
                                    . 'style="color:#2196F3;font-weight:bold;text-decoration:underline">'
                                    . 'Hier klicken um E-Mail zu oeffnen</a>'
                                    . '<br><br><span style="font-size:11px;color:#888">Setup-Token: ' . $token . '</span>'
                                ))
                                ->success()
                                ->persistent()
                                ->send();
                        } else {
                            // Link kopieren
                            \Filament\Notifications\Notification::make()
                                ->title("Einrichtungscode fuer {$stationName}")
                                ->body(new HtmlString(
                                    '<div style="margin-bottom:8px"><strong>Setup-Token:</strong></div>'
                                    . '<div style="font-family:monospace;font-size:11px;padding:6px;background:#f5f5f5;border-radius:4px;word-break:break-all;user-select:all">'
                                    . e($token) . '</div>'
                                    . '<div style="margin-top:8px"><strong>Setup-Link:</strong></div>'
                                    . '<div style="font-size:12px;user-select:all">' . e($setupUrl) . '</div>'
                                    . '<div style="margin-top:8px;font-size:11px;color:#888">Gueltig bis: '
                                    . now()->addHours(24)->format('d.m.Y H:i') . ' Uhr</div>'
                                ))
                                ->success()
                                ->persistent()
                                ->send();
                        }

                        // QR-Code als Session-Flash anzeigen
                        $qrSvg = QrCode::size(300)->margin(2)->generate($token);
                        session()->flash('show_qr_code', true);
                        session()->flash('qr_base64', base64_encode($qrSvg));
                        session()->flash('qr_station', $stationName);
                        session()->flash('qr_token', $token);
                        session()->flash('qr_expires', now()->addHours(24)->format('d.m.Y H:i'));
                    }),

                // ── Geraet aktivieren/deaktivieren ──
                Action::make('toggle_active')
                    ->label(fn (Device $record) => $record->is_active ? 'Deaktivieren' : 'Aktivieren')
                    ->icon(fn (Device $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Device $record) => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Device $record) => $record->is_active
                        ? 'Geraet deaktivieren?'
                        : 'Geraet aktivieren?')
                    ->modalDescription(fn (Device $record) => $record->is_active
                        ? 'Das Geraet kann sich nicht mehr an der POS-App anmelden.'
                        : 'Das Geraet kann sich wieder anmelden.')
                    ->action(fn (Device $record) => $record->update(['is_active' => ! $record->is_active])),

                // ── Geraet komplett loeschen (Soft-Delete) ──
                Action::make('delete_device')
                    ->label('Loeschen')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Geraet endgueltig entfernen?')
                    ->modalDescription(
                        'Das Geraet wird entfernt und muss neu registriert werden. '
                        . 'Die App zeigt automatisch den Setup-Dialog.'
                    )
                    ->action(function (Device $record) {
                        $record->update([
                            'is_active' => false,
                            'device_token_hash' => 'DELETED_' . $record->id,
                        ]);
                        $record->delete();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('deactivate')
                    ->label('Deaktivieren')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->update(['is_active' => false])),

                BulkAction::make('delete')
                    ->label('Loeschen')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Ausgewaehlte Geraete entfernen?')
                    ->modalDescription(
                        'Alle ausgewaehlten Geraete werden entfernt. '
                        . 'Die Apps muessen neu registriert werden.'
                    )
                    ->action(function (Collection $records) {
                        $records->each(function (Device $device) {
                            $device->update([
                                'is_active' => false,
                                'device_token_hash' => 'DELETED_' . $device->id,
                            ]);
                            $device->delete();
                        });
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->headerActions([
                // ── QR-Code fuer MDE-Geraet generieren ──
                Action::make('generate_qr')
                    ->label('QR-Code fuer Station')
                    ->icon('heroicon-o-qr-code')
                    ->color('info')
                    ->form([
                        \Filament\Forms\Components\Select::make('station_id')
                            ->label('Tankstelle')
                            ->options(fn () => GasStation::where('tenant_id', session('tenant_id'))->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (array $data, Action $action) {
                        $token = DeviceInvitation::generateToken();

                        $invitation = DeviceInvitation::create([
                            'tenant_id' => session('tenant_id'),
                            'station_id' => $data['station_id'],
                            'user_id' => auth()->id(),
                            'invited_by' => auth()->id(),
                            'token' => $token,
                            'channel' => 'qr_code',
                            'status' => 'pending',
                            'expires_at' => now()->addHours(24),
                        ]);

                        $stationName = GasStation::find($data['station_id'])?->name ?? 'Station';
                        $expiresAt = now()->addHours(24)->format('d.m.Y H:i');

                        $qrSvg = QrCode::size(300)->margin(2)->generate($token);
                        $qrBase64 = base64_encode($qrSvg);

                        \Filament\Notifications\Notification::make()
                            ->title("QR-Code fuer: {$stationName}")
                            ->body(
                                "Gueltig bis {$expiresAt} Uhr.\n\n"
                                . "Setup-Token:\n{$token}"
                            )
                            ->success()
                            ->persistent()
                            ->send();

                        session()->flash('show_qr_code', true);
                        session()->flash('qr_base64', $qrBase64);
                        session()->flash('qr_station', $stationName);
                        session()->flash('qr_token', $token);
                        session()->flash('qr_expires', $expiresAt);
                    }),

                // ── Mitarbeiter fuer POS einrichten ──
                Action::make('invite_employee')
                    ->label('Mitarbeiter einrichten')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\Select::make('user_id')
                            ->label('Mitarbeiter')
                            ->options(function () {
                                return User::where('tenant_id', session('tenant_id'))
                                    ->where('type', 'employee')
                                    ->where('is_active', true)
                                    ->get()
                                    ->mapWithKeys(fn ($u) => [
                                        $u->id => $u->name . ($u->scan_code ? ' (Code vorhanden)' : ''),
                                    ]);
                            })
                            ->required()
                            ->searchable()
                            ->helperText('Nur aktive Mitarbeiter Ihres Unternehmens.'),

                        \Filament\Forms\Components\Select::make('station_ids')
                            ->label('Tankstelle(n) zuordnen')
                            ->multiple()
                            ->options(fn () => GasStation::where('tenant_id', session('tenant_id'))->pluck('name', 'id'))
                            ->required()
                            ->helperText('Der Mitarbeiter kann sich an diesen Stationen anmelden.'),
                    ])
                    ->action(function (array $data) {
                        $user = User::find($data['user_id']);
                        if (! $user) {
                            \Filament\Notifications\Notification::make()
                                ->title('Fehler')
                                ->body('Mitarbeiter nicht gefunden.')
                                ->danger()
                                ->send();
                            return;
                        }

                        // 1. Scan-Code generieren (falls noch keiner vorhanden)
                        $isNew = false;
                        if (! $user->scan_code) {
                            $user->update(['scan_code' => strtoupper(Str::random(12))]);
                            $isNew = true;
                        }

                        // 2. Mitarbeiter den Stationen zuordnen
                        $stationIds = $data['station_ids'];
                        foreach ($stationIds as $stationId) {
                            if (! $user->gasStations()->where('gas_stations.id', $stationId)->exists()) {
                                $user->gasStations()->attach($stationId, [
                                    'station_role' => 'employee',
                                    'is_primary' => false,
                                    'assigned_at' => now(),
                                ]);
                            }
                        }

                        // 3. QR-Code generieren und anzeigen
                        $scanCode = $user->scan_code;
                        $qrSvg = QrCode::size(300)->margin(2)->generate($scanCode);
                        $qrBase64 = base64_encode($qrSvg);
                        $stationNames = GasStation::whereIn('id', $stationIds)->pluck('name')->join(', ');

                        \Filament\Notifications\Notification::make()
                            ->title("POS-Login fuer {$user->name} eingerichtet!")
                            ->body(
                                ($isNew ? "Neuer Scan-Code generiert.\n" : "Bestehender Scan-Code verwendet.\n")
                                . "Stationen: {$stationNames}\n"
                                . "Scan-Code: {$scanCode}"
                            )
                            ->success()
                            ->persistent()
                            ->send();

                        // QR-Code Modal anzeigen
                        session()->flash('show_qr_code', true);
                        session()->flash('qr_base64', $qrBase64);
                        session()->flash('qr_station', $user->name . ' — Login-Code');
                        session()->flash('qr_token', $scanCode);
                        session()->flash('qr_expires', 'Unbegrenzt gueltig');
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Noch keine Geraete registriert')
            ->emptyStateDescription('Laden Sie Mitarbeiter ein oder generieren Sie einen QR-Code fuer ein MDE-Geraet.')
            ->emptyStateIcon('heroicon-o-device-phone-mobile');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDevices::route('/'),
        ];
    }
}
