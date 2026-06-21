<?php

namespace App\Filament\Partner\Resources\CoolingUnitResource\Pages;

use App\Filament\Partner\Resources\CoolingUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCoolingUnit extends EditRecord
{
    protected static string $resource = CoolingUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
