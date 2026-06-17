<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\PrintAgentResource\Pages;
use App\Models\GasStation;
use App\Models\PrintAgent;
use App\Services\PrintQueueService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filament Resource: Druck-Agenten ("ROSI Print" am Stations-PC).
 * Anlegen + Token erzeugen, Online-Status, gemeldete Drucker, Drucker-Zuordnung
 * der Station, Testdruck.
 */
class PrintAgentResource extends Resource
{
    use \App\Filament\Concerns\HasCatalogPermissions;

    protected static ?string $permissionKey = 'partner.print';

    protected static ?string $model = PrintAgent::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-computer-desktop';

    protected static string|\UnitEnum|null $navigationGroup = 'Drucken';

    protected static ?string $modelLabel = 'Druck-Agent';

    protected static ?string $pluralModelLabel = 'Druck-Agenten';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', session('tenant_id'));
    }

    /** Job-Typen fuer die Drucker-Zuordnung. */
    public static function jobTypes(): array
    {
        return [
            '*' => 'Standard (alle)',
            'voucher_labels' => 'Gutscheine',
            'mhd_labels' => 'MHD',
            'address_labels' => 'Adresse',
            'fuel_theft' => 'Tankbetrug',
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Agent')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('station.name')
                    ->label('Station')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Freigabe')
                    ->badge()
                    ->getStateUsing(fn (PrintAgent $r) => match ($r->status) {
                        PrintAgent::STATUS_PENDING => 'Wartet auf Freigabe',
                        PrintAgent::STATUS_BLOCKED => 'Gesperrt',
                        default => 'Freigegeben',
                    })
                    ->color(fn (PrintAgent $r) => match ($r->status) {
                        PrintAgent::STATUS_PENDING => 'warning',
                        PrintAgent::STATUS_BLOCKED => 'danger',
                        default => 'success',
                    }),

                TextColumn::make('online')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (PrintAgent $r) => $r->online() ? 'Online' : 'Offline')
                    ->color(fn (PrintAgent $r) => $r->online() ? 'success' : 'gray'),

                TextColumn::make('is_default')
                    ->label('Standard')
                    ->badge()
                    ->getStateUsing(fn (PrintAgent $r) => $r->is_default ? 'Standard-Drucker' : '')
                    ->color('info')
                    ->placeholder('—'),

                TextColumn::make('printers')
                    ->label('Gemeldete Drucker')
                    ->badge()
                    ->getStateUsing(fn (PrintAgent $r) => $r->printers ?? [])
                    ->placeholder('—'),

                TextColumn::make('app_version')
                    ->label('Version')
                    ->toggleable(),

                TextColumn::make('last_seen_at')
                    ->label('Zuletzt gesehen')
                    ->since()
                    ->placeholder('nie'),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('station_id')
                    ->label('Station')
                    ->options(fn () => GasStation::where('tenant_id', session('tenant_id'))->pluck('name', 'id')),
            ])
            ->headerActions([
                Action::make('stationInstaller')
                    ->label('Stations-Installer')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->modalHeading('ROSI Print verbinden')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Schliessen')
                    ->modalContent(fn () => view('filament.partner.print-agent-installer', [
                        'stations' => GasStation::where('tenant_id', session('tenant_id'))
                            ->orderBy('name')->pluck('name', 'id'),
                    ])),

                Action::make('createAgent')
                    ->label('Neuer Agent')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Druck-Agent anlegen')
                    ->form([
                        TextInput::make('name')
                            ->label('Name (z.B. Kassen-PC)')
                            ->required()
                            ->maxLength(255),
                        Select::make('station_id')
                            ->label('Station')
                            ->options(fn () => GasStation::where('tenant_id', session('tenant_id'))->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        // Erster Agent der Station wird automatisch Standard
                        $isFirst = ! PrintAgent::where('station_id', $data['station_id'])->exists();

                        $agent = new PrintAgent([
                            'tenant_id' => session('tenant_id'),
                            'station_id' => $data['station_id'],
                            'name' => $data['name'],
                            'is_active' => true,
                            'is_default' => $isFirst,
                        ]);
                        $token = $agent->generateToken();
                        $agent->save();

                        static::showToken($token);
                    }),
            ])
            ->actions([
                // Wartenden Self-Register-Agenten freigeben + Station zuweisen
                Action::make('approve')
                    ->label('Freigeben')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PrintAgent $r) => $r->status === PrintAgent::STATUS_PENDING)
                    ->modalHeading('Agent freigeben')
                    ->modalDescription(fn (PrintAgent $r) => 'PC: ' . ($r->hostname ?: $r->name)
                        . ' — Station zuweisen. Der Agent verbindet sich danach automatisch.')
                    ->form([
                        Select::make('station_id')
                            ->label('Station')
                            ->options(fn () => GasStation::where('tenant_id', session('tenant_id'))->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (PrintAgent $r, array $data) {
                        $isFirst = ! PrintAgent::where('station_id', $data['station_id'])
                            ->where('status', PrintAgent::STATUS_ACTIVE)->exists();

                        $r->update([
                            'tenant_id' => session('tenant_id'),
                            'station_id' => $data['station_id'],
                            'status' => PrintAgent::STATUS_ACTIVE,
                            'is_default' => $isFirst,
                        ]);
                        Notification::make()->success()
                            ->title('Agent freigegeben')
                            ->body('„' . $r->name . '" verbindet sich in Kuerze automatisch.')
                            ->send();
                    }),

                // Drucker-Zuordnung der Station (job_type -> Druckername)
                Action::make('printerMap')
                    ->label('Drucker-Zuordnung')
                    ->icon('heroicon-o-printer')
                    ->modalHeading('Drucker-Zuordnung')
                    ->modalDescription(fn (PrintAgent $r) => 'Gemeldete Drucker: '
                        . (empty($r->printers) ? '— (Agent war noch nicht online)' : implode(', ', $r->printers)))
                    ->fillForm(function (PrintAgent $r) {
                        // Nur Zuordnungen vorbelegen, deren Drucker real gemeldet wird —
                        // veraltete Eintraege (nicht mehr existierende Drucker) fallen raus.
                        $valid = static::printerOptions($r);

                        return [
                            'printer_map' => collect($r->station?->printer_map ?? [])
                                ->filter(fn ($printer) => isset($valid[$printer]))
                                ->map(fn ($printer, $jobType) => ['job_type' => $jobType, 'printer' => $printer])
                                ->values()
                                ->all(),
                        ];
                    })
                    ->form(fn (PrintAgent $r): array => [
                        Repeater::make('printer_map')
                            ->label('Zuordnung Job-Typ → Drucker')
                            ->schema([
                                Select::make('job_type')
                                    ->label('Job-Typ')
                                    ->options(static::jobTypes())
                                    ->required()
                                    ->distinct(),
                                Select::make('printer')
                                    ->label('Drucker')
                                    ->options(static::printerOptions($r))
                                    ->searchable()
                                    ->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel('Zuordnung hinzufügen')
                            ->default([]),
                    ])
                    ->action(function (PrintAgent $r, array $data) {
                        $map = [];
                        foreach ($data['printer_map'] ?? [] as $row) {
                            if (! empty($row['job_type']) && ! empty($row['printer'])) {
                                $map[$row['job_type']] = $row['printer'];
                            }
                        }
                        $r->station?->update(['printer_map' => $map ?: null]);
                        Notification::make()->success()->title('Drucker-Zuordnung gespeichert')->send();
                    }),

                // Testdruck in die Queue legen
                Action::make('testPrint')
                    ->label('Testdruck')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Testdruck senden?')
                    ->modalDescription('Legt einen Test-Druckjob in die Queue. Der Agent druckt ihn in Kürze.')
                    ->action(function (PrintAgent $r) {
                        try {
                            app(PrintQueueService::class)->enqueueFromTemplate(
                                $r->station,
                                'testdruck',
                                [['datum' => now()->format('d.m.Y H:i'), '_number' => 1]],
                                ['reference' => 'TESTDRUCK', 'created_by' => auth()->user()->name ?? 'Dashboard'],
                            );
                            Notification::make()->success()
                                ->title('Testdruck in der Queue')
                                ->body('Der Agent „' . $r->name . '" druckt in Kürze.')
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()->danger()
                                ->title('Testdruck fehlgeschlagen')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Action::make('setDefault')
                    ->label('Als Standard')
                    ->icon('heroicon-o-star')
                    ->color('gray')
                    ->visible(fn (PrintAgent $r) => ! $r->is_default)
                    ->requiresConfirmation()
                    ->modalHeading('Standard-Drucker festlegen?')
                    ->modalDescription('Dieser Agent druckt kuenftig alle Auftraege ohne ausdrueckliches Ziel (z.B. Kasse).')
                    ->action(function (PrintAgent $r) {
                        PrintAgent::where('station_id', $r->station_id)->update(['is_default' => false]);
                        $r->update(['is_default' => true]);
                        Notification::make()->success()->title($r->name . ' ist jetzt Standard-Drucker')->send();
                    }),

                Action::make('regenerateToken')
                    ->label('Token neu')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Neuen Token erzeugen?')
                    ->modalDescription('Der alte Token wird ungültig. Der Agent muss neu konfiguriert werden.')
                    ->action(function (PrintAgent $r) {
                        $token = $r->generateToken();
                        $r->save();
                        static::showToken($token);
                    }),

                DeleteAction::make()->label('Löschen'),
            ])
            ->emptyStateHeading('Keine Druck-Agenten')
            ->emptyStateDescription('Lege einen Agenten an und trage den Token in die „ROSI Print"-App am Stations-PC ein.')
            ->emptyStateIcon('heroicon-o-computer-desktop')
            ->poll('30s');
    }

    /**
     * Drucker-Optionen: nur TATSAECHLICH gemeldete Drucker der Station
     * (von allen aktiven Agenten). Keine Alt-Eintraege aus printer_map mehr,
     * sonst tauchen laengst entfernte Drucker als "Geister" auf.
     */
    protected static function printerOptions(PrintAgent $r): array
    {
        return PrintAgent::where('station_id', $r->station_id)
            ->where('is_active', true)
            ->get()
            ->flatMap(fn (PrintAgent $a) => $a->printers ?? [])
            ->merge($r->printers ?? [])
            ->filter()
            ->unique()
            ->mapWithKeys(fn ($n) => [$n => $n])
            ->all();
    }

    /** Token einmalig als persistente Notification anzeigen (kopierbar). */
    protected static function showToken(string $token): void
    {
        Notification::make()
            ->success()
            ->title('Agent-Token (nur jetzt sichtbar!)')
            ->body($token)
            ->persistent()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrintAgents::route('/'),
        ];
    }
}
