<?php

namespace App\Filament\Partner\Resources\CorporateCustomerResource\Pages;

use App\Filament\Partner\Resources\CorporateCustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCorporateCustomers extends ListRecords
{
    protected static string $resource = CorporateCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
