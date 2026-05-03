<?php

namespace App\Filament\Partner\Resources\ShiftSettlementResource\Pages;

use App\Filament\Partner\Resources\ShiftSettlementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShiftSettlement extends EditRecord
{
    protected static string $resource = ShiftSettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
