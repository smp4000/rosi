<?php

namespace App\Filament\Partner\Resources\GasStationResource\Pages;

use App\Filament\Partner\Resources\GasStationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGasStation extends CreateRecord
{
    protected static string $resource = GasStationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
