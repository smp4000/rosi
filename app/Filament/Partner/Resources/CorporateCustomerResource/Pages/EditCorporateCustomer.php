<?php

namespace App\Filament\Partner\Resources\CorporateCustomerResource\Pages;

use App\Filament\Partner\Resources\CorporateCustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\Enums\ContentTabPosition;

class EditCorporateCustomer extends EditRecord
{
    protected static string $resource = CorporateCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getContentTabLabel(): ?string
    {
        return 'Stammdaten';
    }

    public function getContentTabIcon(): ?string
    {
        return 'heroicon-o-user';
    }

    public function getContentTabPosition(): ?ContentTabPosition
    {
        return ContentTabPosition::Before;
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}
