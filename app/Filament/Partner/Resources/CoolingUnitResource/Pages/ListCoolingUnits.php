<?php

namespace App\Filament\Partner\Resources\CoolingUnitResource\Pages;

use App\Filament\Partner\Resources\CoolingUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCoolingUnits extends ListRecords
{
    protected static string $resource = CoolingUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Kühlmöbel anlegen'),
        ];
    }
}
