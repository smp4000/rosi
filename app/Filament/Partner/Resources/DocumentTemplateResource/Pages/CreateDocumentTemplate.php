<?php

namespace App\Filament\Partner\Resources\DocumentTemplateResource\Pages;

use App\Filament\Partner\Resources\DocumentTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDocumentTemplate extends CreateRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = session('tenant_id');

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
