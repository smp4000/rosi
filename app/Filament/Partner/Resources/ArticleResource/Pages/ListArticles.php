<?php

namespace App\Filament\Partner\Resources\ArticleResource\Pages;

use App\Filament\Partner\Resources\ArticleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArticles extends ListRecords
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Artikel anlegen'),
        ];
    }
}
