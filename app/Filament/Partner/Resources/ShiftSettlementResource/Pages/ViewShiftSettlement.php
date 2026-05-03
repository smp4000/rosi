<?php

namespace App\Filament\Partner\Resources\ShiftSettlementResource\Pages;

use App\Filament\Partner\Resources\ShiftSettlementResource;
use Filament\Resources\Pages\ViewRecord;

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
}
