<?php

namespace App\Filament\Partner\Resources\ArticleGroupResource\Pages;

use App\Filament\Partner\Resources\ArticleGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListArticleGroups extends ListRecords
{
    protected static string $resource = ArticleGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Eigene Artikelgruppe anlegen'),
        ];
    }
}
