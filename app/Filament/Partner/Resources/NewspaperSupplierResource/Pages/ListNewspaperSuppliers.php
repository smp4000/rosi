<?php

namespace App\Filament\Partner\Resources\NewspaperSupplierResource\Pages;

use App\Filament\Partner\Resources\NewspaperSupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNewspaperSuppliers extends ListRecords
{
    protected static string $resource = NewspaperSupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
