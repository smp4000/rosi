<?php

namespace App\Filament\Partner\Resources\GasStationResource\Pages;

use App\Filament\Partner\Resources\GasStationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGasStations extends ListRecords
{
    protected static string $resource = GasStationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tankstelle anlegen'),
        ];
    }
}
