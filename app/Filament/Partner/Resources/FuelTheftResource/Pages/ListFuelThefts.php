<?php

namespace App\Filament\Partner\Resources\FuelTheftResource\Pages;

use App\Filament\Partner\Resources\FuelTheftResource;
use App\Filament\Partner\Resources\FuelTheftResource\Widgets\FuelTheftStatsOverview;
use App\Models\FuelTheft;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListFuelThefts extends ListRecords
{
    protected static string $resource = FuelTheftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tankbetrug melden')
                ->icon('heroicon-o-plus'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FuelTheftStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        $tenantId = auth()->user()?->tenant_id;
        $baseQuery = FuelTheft::where('tenant_id', $tenantId);

        $activeCount = (clone $baseQuery)
            ->whereNotIn('status', ['resolved', 'rejected'])
            ->count();

        $archivedCount = (clone $baseQuery)
            ->whereIn('status', ['resolved', 'rejected'])
            ->count();

        return [
            'aktiv' => Tab::make('Aktiv')
                ->icon('heroicon-o-clipboard-document-list')
                ->badge($activeCount)
                ->modifyQueryUsing(fn ($query) => $query->whereNotIn('status', ['resolved', 'rejected'])),

            'archiv' => Tab::make('Archiv')
                ->icon('heroicon-o-archive-box')
                ->badge($archivedCount)
                ->modifyQueryUsing(fn ($query) => $query->whereIn('status', ['resolved', 'rejected'])),

            'alle' => Tab::make('Alle')
                ->icon('heroicon-o-list-bullet')
                ->badge($activeCount + $archivedCount),
        ];
    }
}
