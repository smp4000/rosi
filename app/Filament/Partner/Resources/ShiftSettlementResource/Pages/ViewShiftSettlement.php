<?php

namespace App\Filament\Partner\Resources\ShiftSettlementResource\Pages;

use App\Filament\Partner\Resources\ShiftSettlementResource;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Detailansicht einer Schichtabrechnung mit allen Anhaengen.
 * Nutzt eine custom Blade-View statt einer Schema/Infolist.
 */
class ViewShiftSettlement extends ViewRecord
{
    protected static string $resource = ShiftSettlementResource::class;

    protected string $view = 'filament.partner.resources.shift-settlement.view';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->load([
            'gasStation',
            'user',
            'coinRolls',
            'counters',
            'safeDeposits',
            'returns',
            'checkAnswers.question',
        ]);
    }

    public function getTitle(): string
    {
        $stationName = $this->record->gasStation?->name ?? '—';
        $startedAt = $this->record->started_at?->format('d.m.Y H:i') ?? '—';

        return "Schicht {$stationName} ({$startedAt})";
    }

    public function getBreadcrumb(): string
    {
        return $this->record->started_at?->format('d.m.Y H:i') ?? 'Schicht';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('PDF herunterladen')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(function (): StreamedResponse {
                    $record = $this->record;
                    $filename = 'Schichtabrechnung_'
                        . $record->started_at?->format('Y-m-d_Hi')
                        . '_' . str($record->gasStation?->name ?? 'Station')->slug()
                        . '.pdf';

                    $pdf = Pdf::loadView('filament.partner.resources.shift-settlement.pdf', [
                        'record' => $record,
                    ])->setPaper('a4');

                    return response()->streamDownload(
                        fn () => print($pdf->output()),
                        $filename,
                    );
                }),

            Action::make('edit')
                ->label('Bearbeiten')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->url(fn () => ShiftSettlementResource::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
