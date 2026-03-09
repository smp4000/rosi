<?php

namespace App\Filament\Partner\Resources\GasStationResource\Pages;

use App\Filament\Partner\Resources\GasStationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGasStation extends EditRecord
{
    protected static string $resource = GasStationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
