<?php

namespace App\Filament\Resources\ArticleGroupResource\Pages;

use App\Filament\Resources\ArticleGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArticleGroups extends ListRecords
{
    protected static string $resource = ArticleGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Artikelgruppe anlegen'),
        ];
    }
}
