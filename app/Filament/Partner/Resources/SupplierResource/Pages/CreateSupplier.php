<?php

namespace App\Filament\Partner\Resources\SupplierResource\Pages;

use App\Filament\Partner\Resources\SupplierResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['status'] ??= 'active';
        return $data;
    }
}
