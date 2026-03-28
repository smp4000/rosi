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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Filament Resource: POS-Geraete verwalten.
 * Partner sieht hier alle registrierten Geraete (Zebra MDE + Handys)
 * und kann neue Geraete einladen oder QR-Codes generieren.
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
                TextColumn::make('station.name')
                    ->label('Tankstelle')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Mitarbeiter')
                    ->placeholder('— Stations-Geraet —')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('device_type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'mde' => 'MDE / Firmengeraet',
                        'personal' => 'Eigenes Handy',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'mde' => 'info',
                        'personal' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('device_name')
                    ->label('Geraet')
                    ->placeholder('Unbekannt'),

                TextColumn::make('device_os')
                    ->label('System')
                    ->placeholder('—'),

                TextColumn::make('app_version')
                    ->label('App-Version')
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
                    ->label('Geraetetyp')
                    ->options([
                        'mde' => 'MDE / Firmengeraet',
                        'personal' => 'Eigenes Handy',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Aktiv')
                    ->trueLabel('Nur aktive')
                    ->falseLabel('Nur deaktivierte'),
            ])
            ->actions([
                // Geraet aktivieren/deaktivieren
                Action::make('toggle_active')
                    ->label(fn (Device $record) => $record->is_active ? 'Deaktivieren' : 'Aktivieren')
                    ->icon(fn (Device $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Device $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Device $record) => $record->is_active
                        ? 'Geraet deaktivieren?'
                        : 'Geraet aktivieren?')
                    ->modalDescription(fn (Device $record) => $record->is_active
                        ? 'Das Geraet kann sich nicht mehr an der POS-App anmelden.'
                        : 'Das Geraet kann sich wieder anmelden.')
                    ->action(fn (Device $record) => $record->update(['is_active' => ! $record->is_active])),
            ])
            ->bulkActions([
                BulkAction::make('deactivate')
                    ->label('Deaktivieren')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each->update(['is_active' => false])),
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
                        $setupToken = $token;
                        $expiresAt = now()->addHours(24)->format('d.m.Y H:i');

                        // QR-Code als SVG generieren
                        $qrSvg = QrCode::size(300)->margin(2)->generate($setupToken);
                        $qrBase64 = base64_encode($qrSvg);

                        \Filament\Notifications\Notification::make()
                            ->title("QR-Code fuer: {$stationName}")
                            ->body(
                                "Gueltig bis {$expiresAt} Uhr.\n\n" .
                                "Setup-Token:\n{$setupToken}"
                            )
                            ->success()
                            ->persistent()
                            ->send();

                        // QR-Code in Session fuer die Blade-Anzeige
                        session()->flash('show_qr_code', true);
                        session()->flash('qr_base64', $qrBase64);
                        session()->flash('qr_station', $stationName);
                        session()->flash('qr_token', $setupToken);
                        session()->flash('qr_expires', $expiresAt);
                    }),

                // ── Mitarbeiter per Link einladen ──
                Action::make('invite_employee')
                    ->label('Mitarbeiter einladen')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\Select::make('station_id')
                            ->label('Tankstelle')
                            ->options(fn () => GasStation::where('tenant_id', session('tenant_id'))->pluck('name', 'id'))
                            ->required(),

                        \Filament\Forms\Components\Select::make('user_id')
                            ->label('Mitarbeiter')
                            ->options(fn () => User::where('tenant_id', session('tenant_id'))
                                ->where('type', 'employee')
                                ->get()
                                ->pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        \Filament\Forms\Components\Select::make('channel')
                            ->label('Versand per')
                            ->options([
                                'email' => 'E-Mail',
                                'sms' => 'SMS',
                                'whatsapp' => 'WhatsApp',
                                'qr_code' => 'Nur QR-Code (kein Versand)',
                            ])
                            ->default('email')
                            ->required(),

                        \Filament\Forms\Components\TextInput::make('valid_hours')
                            ->label('Gueltig fuer (Stunden)')
                            ->numeric()
                            ->default(72)
                            ->minValue(1)
                            ->maxValue(720)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $token = DeviceInvitation::generateToken();

                        $invitation = DeviceInvitation::create([
                            'tenant_id' => session('tenant_id'),
                            'station_id' => $data['station_id'],
                            'user_id' => $data['user_id'],
                            'invited_by' => auth()->id(),
                            'token' => $token,
                            'channel' => $data['channel'],
                            'status' => 'pending',
                            'expires_at' => now()->addHours($data['valid_hours']),
                        ]);

                        $setupUrl = url("/pos-setup/{$token}");

                        // TODO: E-Mail/SMS versand implementieren
                        // Vorerst zeigen wir den Link an

                        \Filament\Notifications\Notification::make()
                            ->title('Einladung erstellt!')
                            ->body("Link: {$setupUrl}")
                            ->success()
                            ->persistent()
                            ->send();
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
