<?php

namespace App\Filament\Partner\Resources\SupplierResource\Pages;

use App\Filament\Partner\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSupplier extends ViewRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }
}
