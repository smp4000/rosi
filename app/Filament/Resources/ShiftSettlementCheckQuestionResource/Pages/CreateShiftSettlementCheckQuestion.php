<?php

namespace App\Filament\Resources\ShiftSettlementCheckQuestionResource\Pages;

use App\Filament\Resources\ShiftSettlementCheckQuestionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShiftSettlementCheckQuestion extends CreateRecord
{
    protected static string $resource = ShiftSettlementCheckQuestionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
