<?php

namespace App\Filament\Partner\Resources\ShiftSettlementResource\Pages;

use App\Filament\Partner\Resources\ShiftSettlementResource;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Model;

/**
 * Detailansicht einer Schichtabrechnung mit allen Anhaengen.
 */
class ViewShiftSettlement extends Page
{
    protected static string $resource = ShiftSettlementResource::class;

    protected string $view = 'filament.partner.resources.shift-settlement.view';

    public Model $record;

    public function mount(string $record): void
    {
        $this->record = ShiftSettlementResource::getEloquentQuery()
            ->with([
                'gasStation',
                'user',
                'coinRolls',
                'counters',
                'safeDeposits',
                'returns',
                'checkAnswers.question',
            ])
            ->findOrFail($record);
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
