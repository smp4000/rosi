<?php

namespace App\Filament\Partner\Resources\DepreciationEntryResource\Pages;

use App\Filament\Partner\Resources\DepreciationEntryResource;
use App\Models\GasStation;
use App\Services\ReportService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListDepreciationEntries extends ListRecords
{
    protected static string $resource = DepreciationEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createReport')
                ->label('Tagesbericht erstellen')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->visible(fn () => DepreciationEntryResource::userCan('report'))
                ->schema([
                    Select::make('station_id')
                        ->label('Station')
                        ->placeholder('Alle Stationen')
                        ->options(fn () => GasStation::where('tenant_id', session('tenant_id'))->pluck('name', 'id')),
                    DatePicker::make('from')
                        ->label('Von')
                        ->required()
                        ->displayFormat('d.m.Y')
                        ->default(now()),
                    DatePicker::make('until')
                        ->label('Bis')
                        ->required()
                        ->displayFormat('d.m.Y')
                        ->default(now()),
                ])
                ->action(function (array $data) {
                    $from = Carbon::parse($data['from']);
                    $to = Carbon::parse($data['until']);
                    if ($to->lt($from)) {
                        [$from, $to] = [$to, $from];
                    }

                    $report = app(ReportService::class)->generateDepreciationReport(
                        tenantId: session('tenant_id'),
                        stationId: $data['station_id'] ?? null,
                        from: $from,
                        to: $to,
                    );

                    Notification::make()
                        ->success()
                        ->title('Bericht erstellt')
                        ->body($report->meta['count'] . ' Abschriften · Gesamt-EK '
                            . number_format($report->meta['total_ek'], 2, ',', '.') . ' €. Im Archiv „Berichte" abrufbar.')
                        ->send();
                }),
        ];
    }
}
