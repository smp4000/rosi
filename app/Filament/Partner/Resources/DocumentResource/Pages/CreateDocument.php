<?php

namespace App\Filament\Partner\Resources\DocumentResource\Pages;

use App\Filament\Partner\Resources\DocumentResource;
use App\Models\DocumentTemplate;
use App\Services\DocumentService;
use Filament\Resources\Pages\CreateRecord;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = session('tenant_id');
        $data['created_by'] = auth()->id();
        $data['status'] = 'draft';
        $data['version'] = 1;

        // Platzhalter im Inhalt ersetzen wenn Template und Mitarbeiter gewaehlt
        if (! empty($data['template_id']) && ! empty($data['user_id'])) {
            $template = DocumentTemplate::find($data['template_id']);
            $employee = \App\Models\User::find($data['user_id']);
            $station = null;

            if ($template && $employee) {
                $service = app(DocumentService::class);
                $variables = $service->buildVariableMap($employee, $station);
                $data['content'] = $service->replacePlaceholders($data['content'], $variables);
                $data['title'] = $service->replacePlaceholders($data['title'], $variables);
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
