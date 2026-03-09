<?php

namespace App\Filament\Partner\Resources\GasStationResource\Pages;

use App\Filament\Partner\Resources\GasStationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGasStation extends ViewRecord
{
    protected static string $resource = GasStationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
