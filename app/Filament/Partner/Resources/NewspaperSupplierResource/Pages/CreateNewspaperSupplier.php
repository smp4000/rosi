<?php

namespace App\Filament\Partner\Resources\NewspaperSupplierResource\Pages;

use App\Filament\Partner\Resources\NewspaperSupplierResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNewspaperSupplier extends CreateRecord
{
    protected static string $resource = NewspaperSupplierResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;
        return $data;
    }
}
