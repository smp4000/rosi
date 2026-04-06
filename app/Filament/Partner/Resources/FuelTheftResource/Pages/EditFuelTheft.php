<?php

namespace App\Filament\Partner\Resources\FuelTheftResource\Pages;

use App\Filament\Partner\Resources\FuelTheftResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFuelTheft extends EditRecord
{
    protected static string $resource = FuelTheftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Kennzeichen uppercase
        if (! empty($data['license_plate'])) {
            $data['license_plate'] = strtoupper(trim($data['license_plate']));
        }

        return $data;
    }
}
