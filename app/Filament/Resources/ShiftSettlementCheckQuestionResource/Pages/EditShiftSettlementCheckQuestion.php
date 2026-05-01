<?php

namespace App\Filament\Resources\ShiftSettlementCheckQuestionResource\Pages;

use App\Filament\Resources\ShiftSettlementCheckQuestionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShiftSettlementCheckQuestion extends EditRecord
{
    protected static string $resource = ShiftSettlementCheckQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
