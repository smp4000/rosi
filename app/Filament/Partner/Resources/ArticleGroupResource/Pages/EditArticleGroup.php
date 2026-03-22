<?php

namespace App\Filament\Partner\Resources\ArticleGroupResource\Pages;

use App\Filament\Partner\Resources\ArticleGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArticleGroup extends EditRecord
{
    protected static string $resource = ArticleGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->tenant_id !== null),
        ];
    }
}
