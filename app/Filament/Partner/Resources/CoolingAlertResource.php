<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\CoolingAlertResource\Pages;
use App\Models\CoolingAlert;
use App\Models\CoolingMeasure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Stoerungen/Alarme der Kuehlmoebel — anzeigen + HACCP-Maßnahme dokumentieren.
 */
class CoolingAlertResource extends Resource
{
    use \App\Filament\Concerns\HasCatalogPermissions;

    protected static ?string $permissionKey = 'partner.temperature';

    protected static ?string $model = CoolingAlert::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|\UnitEnum|null $navigationGroup = 'Temperatur';

    protected static ?string $modelLabel = 'Störung';

    protected static ?string $pluralModelLabel = 'Störungen';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = CoolingAlert::query()->where('status', 'active')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('unit.name')->label('Kühlmöbel')->searchable()->sortable(),
                TextColumn::make('type')
                    ->label('Störung')
                    ->badge()
                    ->formatStateUsing(fn ($state) => CoolingAlert::TYPE_LABELS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'high', 'low' => 'danger', 'offline' => 'warning', 'lowbat' => 'warning', default => 'gray',
                    }),
                TextColumn::make('value')
                    ->label('Wert')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : number_format((float) $state, 1, ',', '.') . ' °C'),
                TextColumn::make('threshold')
                    ->label('Grenze')
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : number_format((float) $state, 1, ',', '.') . ' °C'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'active' ? 'Aktiv' : 'Behoben')
                    ->color(fn ($state) => $state === 'active' ? 'danger' : 'success'),
                TextColumn::make('started_at')->label('Seit')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('ended_at')->label('Bis')->dateTime('d.m.Y H:i')->placeholder('—'),
                TextColumn::make('acknowledged_at')
                    ->label('Maßnahme')
                    ->formatStateUsing(fn ($state, CoolingAlert $r) => $state
                        ? ('#' . ($r->unit?->device_number ?? '?') . ' ' . collect($r->measures ?? [])->map(fn ($m) => "M$m")->implode(' '))
                        : '—'),
            ])
            ->defaultSort('started_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label('Status')->options(['active' => 'Aktiv', 'resolved' => 'Behoben']),
                SelectFilter::make('type')->label('Typ')->options(CoolingAlert::TYPE_LABELS),
            ])
            ->actions([
                Action::make('acknowledge')
                    ->label('Maßnahme dokumentieren')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->fillForm(fn (CoolingAlert $record) => [
                        'measures' => $record->measures ?? [],
                        'control_value' => $record->control_value,
                        'ticket_number' => $record->ticket_number,
                        'note' => $record->note,
                    ])
                    ->form([
                        CheckboxList::make('measures')
                            ->label('Maßnahme(n) – ARAL-Katalog')
                            ->options(fn () => CoolingMeasure::forTenant(session('tenant_id'))
                                ->mapWithKeys(fn ($m) => [$m->number => $m->number . '. ' . $m->text])
                                ->toArray())
                            ->columns(1),
                        TextInput::make('control_value')
                            ->label('Kontrollmessung (°C)')
                            ->numeric(),
                        TextInput::make('ticket_number')
                            ->label('Ticketnummer (bei Maßnahme 5)')
                            ->maxLength(100),
                        Textarea::make('note')->label('Bemerkung')->maxLength(500),
                    ])
                    ->action(function (CoolingAlert $record, array $data) {
                        $record->update([
                            'measures' => array_values(array_map('intval', $data['measures'] ?? [])),
                            'control_value' => $data['control_value'] ?? null,
                            'ticket_number' => $data['ticket_number'] ?? null,
                            'note' => $data['note'] ?? null,
                            'acknowledged_at' => now(),
                            'acknowledged_by' => auth()->id(),
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('Maßnahme dokumentiert')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoolingAlerts::route('/'),
        ];
    }
}
