<?php

namespace App\Filament\Partner\Resources\ArticleGroupResource\Pages;

use App\Filament\Partner\Resources\ArticleGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateArticleGroup extends CreateRecord
{
    protected static string $resource = ArticleGroupResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()?->tenant_id;
        $data['is_system'] = false;

        return $data;
    }
}
