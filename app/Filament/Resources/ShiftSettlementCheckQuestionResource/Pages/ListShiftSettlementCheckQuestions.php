<?php

namespace App\Filament\Resources\ShiftSettlementCheckQuestionResource\Pages;

use App\Filament\Resources\ShiftSettlementCheckQuestionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListShiftSettlementCheckQuestions extends ListRecords
{
    protected static string $resource = ShiftSettlementCheckQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Prueffrage anlegen'),
        ];
    }
}
