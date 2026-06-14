<?php

namespace App\Filament\Partner\Resources;

use App\Filament\Partner\Resources\GeneratedReportResource\Pages;
use App\Models\GeneratedReport;
use Filament\Resources\Resource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

/**
 * Filament Resource: Berichts-Archiv im Partner-Dashboard.
 * Listet alle erzeugten PDF-Berichte (Abschriften, MHD, ...) mit Download.
 */
class GeneratedReportResource extends Resource
{
    use \App\Filament\Concerns\HasCatalogPermissions;

    protected static ?string $permissionKey = 'partner.reports';

    protected static ?string $model = GeneratedReport::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Bistro';

    protected static ?string $modelLabel = 'Bericht';

    protected static ?string $pluralModelLabel = 'Berichte';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Erstellt am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Titel')
                    ->weight('bold')
                    ->searchable()
                    ->limit(45),

                TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'depreciation' => 'Abschriften',
                        'mhd' => 'MHD',
                        default => $state,
                    })
                    ->color('info'),

                TextColumn::make('station.name')
                    ->label('Station')
                    ->default('Alle Stationen')
                    ->toggleable(),

                TextColumn::make('period_from')
                    ->label('Zeitraum')
                    ->getStateUsing(function (GeneratedReport $r) {
                        if (! $r->period_from) {
                            return '—';
                        }
                        $from = $r->period_from->format('d.m.Y');
                        return $r->period_to && ! $r->period_to->isSameDay($r->period_from)
                            ? $from . ' – ' . $r->period_to->format('d.m.Y')
                            : $from;
                    }),

                TextColumn::make('meta.count')
                    ->label('Positionen')
                    ->alignEnd()
                    ->getStateUsing(fn (GeneratedReport $r) => $r->meta['count'] ?? 0),

                TextColumn::make('meta.total_ek')
                    ->label('Gesamt-EK')
                    ->alignEnd()
                    ->getStateUsing(fn (GeneratedReport $r) => isset($r->meta['total_ek'])
                        ? number_format($r->meta['total_ek'], 2, ',', '.') . ' €'
                        : '—'),

                TextColumn::make('user.name')
                    ->label('Erstellt von')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Typ')
                    ->options(['depreciation' => 'Abschriften', 'mhd' => 'MHD']),
            ])
            ->actions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->visible(fn () => static::userCan('download', ['list']))
                    ->action(function (GeneratedReport $record) {
                        if (! Storage::disk('local')->exists($record->file_path)) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Datei nicht gefunden')
                                ->send();
                            return;
                        }

                        return Storage::disk('local')->download(
                            $record->file_path,
                            basename($record->file_path),
                        );
                    }),

                DeleteAction::make()
                    ->label('Loeschen')
                    ->after(function (GeneratedReport $record) {
                        Storage::disk('local')->delete($record->file_path);
                    }),
            ])
            ->emptyStateHeading('Keine Berichte')
            ->emptyStateDescription('Erzeuge einen Bericht unter „Abschriften".')
            ->emptyStateIcon('heroicon-o-rectangle-stack');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGeneratedReports::route('/'),
        ];
    }
}
