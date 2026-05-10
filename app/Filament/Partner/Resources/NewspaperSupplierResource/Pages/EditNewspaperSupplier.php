<?php

namespace App\Filament\Partner\Resources\NewspaperSupplierResource\Pages;

use App\Filament\Partner\Resources\NewspaperSupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNewspaperSupplier extends EditRecord
{
    protected static string $resource = NewspaperSupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make(), Actions\DeleteAction::make()];
    }
}
