<?php

namespace App\Filament\Partner\Resources\CoolingUnitResource\Pages;

use App\Filament\Partner\Resources\CoolingUnitResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCoolingUnit extends CreateRecord
{
    protected static string $resource = CoolingUnitResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()?->tenant_id;

        return $data;
    }
}
