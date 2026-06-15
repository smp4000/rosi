<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\PrintJobResource\Pages;
use App\Models\GasStation;
use App\Models\PrintJob;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filament Resource: Druckaufträge (Queue). Sichtbarkeit + Retry fehlgeschlagener
 * oder abgelaufener Jobs.
 */
class PrintJobResource extends Resource
{
    use \App\Filament\Concerns\HasCatalogPermissions;

    protected static ?string $permissionKey = 'partner.print';

    protected static ?string $model = PrintJob::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Drucken';

    protected static ?string $modelLabel = 'Druckauftrag';

    protected static ?string $pluralModelLabel = 'Druckaufträge';

    protected static ?int $navigationSort = 2;

    private const STATUS_LABELS = [
        'pending' => 'Wartet',
        'printing' => 'Druckt',
        'done' => 'Gedruckt',
        'failed' => 'Fehler',
        'expired' => 'Abgelaufen',
    ];

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', session('tenant_id'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Erstellt')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('station.name')
                    ->label('Station')
                    ->toggleable()
                    ->searchable(),

                TextColumn::make('job_type')
                    ->label('Typ')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('reference')
                    ->label('Referenz')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('printer_name')
                    ->label('Drucker')
                    ->placeholder('auto')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'done' => 'success',
                        'printing' => 'info',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('attempts')
                    ->label('Versuche')
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('error_message')
                    ->label('Fehler')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('created_by')
                    ->label('Von')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('printed_at')
                    ->label('Gedruckt')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('station_id')
                    ->label('Station')
                    ->options(fn () => GasStation::where('tenant_id', session('tenant_id'))->pluck('name', 'id')),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::STATUS_LABELS),
            ])
            ->actions([
                Action::make('retry')
                    ->label('Erneut')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Erneut drucken?')
                    ->visible(fn (PrintJob $r) => in_array($r->status, ['failed', 'expired'], true))
                    ->action(function (PrintJob $r) {
                        $ttl = (int) (config("printing.ttl_minutes.{$r->job_type}")
                            ?? config('printing.default_ttl_minutes', 15));
                        $r->update([
                            'status' => PrintJob::STATUS_PENDING,
                            'expires_at' => now()->addMinutes($ttl),
                            'error_message' => null,
                            'agent_id' => null,
                        ]);
                        Notification::make()->success()
                            ->title('Wieder in der Queue')
                            ->body('Der Agent druckt den Job erneut.')
                            ->send();
                    }),

                DeleteAction::make()->label('Löschen'),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('Keine Druckaufträge')
            ->emptyStateIcon('heroicon-o-queue-list')
            ->poll('15s');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrintJobs::route('/'),
        ];
    }
}
