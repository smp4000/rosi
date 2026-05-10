<?php

namespace App\Filament\Partner\Resources\SupplierResource\Pages;

use App\Filament\Partner\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }
}
