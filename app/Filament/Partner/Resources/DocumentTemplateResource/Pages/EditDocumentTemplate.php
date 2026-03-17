<?php

namespace App\Filament\Partner\Resources\DocumentTemplateResource\Pages;

use App\Filament\Partner\Resources\DocumentTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocumentTemplate extends EditRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => ! $this->record->isGlobal()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
