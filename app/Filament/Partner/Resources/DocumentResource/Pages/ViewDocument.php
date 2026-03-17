<?php

namespace App\Filament\Partner\Resources\DocumentResource\Pages;

use App\Filament\Partner\Resources\DocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDocument extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isDraft()),
        ];
    }
}
