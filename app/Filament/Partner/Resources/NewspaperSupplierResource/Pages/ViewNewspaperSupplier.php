<?php

namespace App\Filament\Partner\Resources\NewspaperSupplierResource\Pages;

use App\Filament\Partner\Resources\NewspaperSupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewNewspaperSupplier extends ViewRecord
{
    protected static string $resource = NewspaperSupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }
}
