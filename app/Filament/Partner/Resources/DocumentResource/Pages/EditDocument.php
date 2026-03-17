<?php

namespace App\Filament\Partner\Resources\DocumentResource\Pages;

use App\Filament\Partner\Resources\DocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isDraft()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
