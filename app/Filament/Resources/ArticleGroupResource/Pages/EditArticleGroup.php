<?php

namespace App\Filament\Resources\ArticleGroupResource\Pages;

use App\Filament\Resources\ArticleGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditArticleGroup extends EditRecord
{
    protected static string $resource = ArticleGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
